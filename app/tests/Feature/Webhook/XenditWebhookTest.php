<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Models\ProductVariant;
use App\Models\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->webhookToken = 'secret-test-webhook-token-999';
    config()->set('services.xendit.webhook_token', $this->webhookToken);

    $this->variant = ProductVariant::create([
        'product_id'     => 1,
        'sku'            => 'VARIANT-01',
        'name'           => 'Test Product',
        'price'          => 100000.00,
        'stock_physical' => 10,
        'stock_reserved' => 2, // 2 reserved for the test order
        'is_active'      => true,
    ]);

    $this->order = Order::create([
        'guest_email'     => 'buyer@example.com',
        'guest_phone'     => '08123456789',
        'status'          => OrderStatus::PendingPayment,
        'subtotal'        => 200000.00,
        'shipping_cost'   => 0.00,
        'total'           => 200000.00,
        'idempotency_key' => 'idemp-webhook-test-1',
    ]);

    $this->reservation = InventoryReservation::create([
        'order_id'           => $this->order->id,
        'product_variant_id' => $this->variant->id,
        'quantity'           => 2,
        'status'             => 'reserved',
        'reserved_at'        => now(),
    ]);

    $this->payment = Payment::create([
        'order_id' => $this->order->id,
        'amount'   => 200000.00,
        'status'   => PaymentStatus::Pending,
    ]);
});

test('valid xendit callback marks order as processing and converts reservations to physical deduction', function () {
    $payload = [
        'event' => 'payment.succeeded',
        'data'  => [
            'id'           => 'pay-unique-xendit-001',
            'reference_id' => $this->order->id,
            'status'       => 'SUCCEEDED',
            'amount'       => 200000.00,
            'currency'     => 'IDR',
        ],
    ];

    $response = $this->withHeaders([
        'x-callback-token' => $this->webhookToken,
    ])->postJson('/api/webhooks/xendit', $payload);

    $response->assertStatus(200)
        ->assertJson(['status' => 'acknowledged']);

    // Order transitioned to processing
    expect($this->order->fresh()->status)->toBe(OrderStatus::Processing);

    // Payment marked as paid
    $payment = $this->payment->fresh();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull();

    // Callback recorded
    expect(PaymentCallback::where('xendit_payment_id', 'pay-unique-xendit-001')->exists())->toBeTrue();

    // FR-INV-004 & FR-INV-005: Reservation converted and physical stock deducted
    expect($this->reservation->fresh()->status)->toBe('converted')
        ->and($this->variant->fresh()->stock_physical)->toBe(8) // 10 - 2
        ->and($this->variant->fresh()->stock_reserved)->toBe(0) // 2 - 2
        ->and($this->variant->fresh()->available_stock)->toBe(8);
});

test('duplicate callback is handled idempotently without duplicate stock deductions per FR-PAY-005', function () {
    $payload = [
        'event' => 'payment.succeeded',
        'data'  => [
            'id'           => 'pay-duplicate-001',
            'reference_id' => $this->order->id,
            'status'       => 'SUCCEEDED',
            'amount'       => 200000.00,
        ],
    ];

    // First delivery
    $response1 = $this->withHeaders(['x-callback-token' => $this->webhookToken])
        ->postJson('/api/webhooks/xendit', $payload);
    $response1->assertStatus(200);

    $physicalAfterFirst = $this->variant->fresh()->stock_physical;
    expect($physicalAfterFirst)->toBe(8);

    // Duplicate delivery
    $response2 = $this->withHeaders(['x-callback-token' => $this->webhookToken])
        ->postJson('/api/webhooks/xendit', $payload);
    $response2->assertStatus(200);

    // Physical stock remains 8 (not decremented twice!)
    expect($this->variant->fresh()->stock_physical)->toBe(8)
        ->and(PaymentCallback::where('xendit_payment_id', 'pay-duplicate-001')->count())->toBe(1);
});

test('late payment on expired order leaves order expired and flags manual refund per FR-PAY-006', function () {
    // Order already expired
    $this->order->update(['status' => OrderStatus::Expired]);

    $payload = [
        'event' => 'payment.succeeded',
        'data'  => [
            'id'           => 'pay-late-001',
            'reference_id' => $this->order->id,
            'status'       => 'SUCCEEDED',
            'amount'       => 200000.00,
        ],
    ];

    $response = $this->withHeaders(['x-callback-token' => $this->webhookToken])
        ->postJson('/api/webhooks/xendit', $payload);

    $response->assertStatus(200);

    // FR-PAY-006 Rule 1 & 4: Order remains expired, not reactivated
    expect($this->order->fresh()->status)->toBe(OrderStatus::Expired);

    // FR-PAY-006 Rule 2: Payment logs late payment detection
    $payment = $this->payment->fresh();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->late_payment_detected_at)->not->toBeNull();

    // FR-PAY-006 Rule 3: Flagged for manual refund
    $refund = Refund::where('order_id', $this->order->id)->first();
    expect($refund)->not->toBeNull()
        ->and($refund->status)->toBe(RefundStatus::ManualRefundPending)
        ->and($refund->amount)->toBe('200000.00');
});

test('partial payment amount mismatch is flagged and does not transition order per TBD-05', function () {
    $payload = [
        'event' => 'payment.succeeded',
        'data'  => [
            'id'           => 'pay-partial-001',
            'reference_id' => $this->order->id,
            'status'       => 'SUCCEEDED',
            'amount'       => 150000.00, // Less than order total 200,000!
        ],
    ];

    $response = $this->withHeaders(['x-callback-token' => $this->webhookToken])
        ->postJson('/api/webhooks/xendit', $payload);

    $response->assertStatus(200);

    // Order must NOT transition to processing
    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);

    // Anomaly recorded
    $callback = PaymentCallback::where('xendit_payment_id', 'pay-partial-001')->first();
    expect($callback)->not->toBeNull()
        ->and($callback->event_type)->toBe('payment.partial_anomaly');
});

test('webhook rejects invalid or missing verification token with 401', function () {
    $payload = [
        'event' => 'payment.succeeded',
        'data'  => [
            'id'           => 'pay-unauth-001',
            'reference_id' => $this->order->id,
            'status'       => 'SUCCEEDED',
            'amount'       => 200000.00,
        ],
    ];

    // Missing token
    $this->postJson('/api/webhooks/xendit', $payload)->assertStatus(401);

    // Wrong token
    $this->withHeaders(['x-callback-token' => 'invalid-token-string'])
        ->postJson('/api/webhooks/xendit', $payload)
        ->assertStatus(401);
});
