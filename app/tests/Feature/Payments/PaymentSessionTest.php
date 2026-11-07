<?php

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.xendit.secret_key', 'xnd_development_dummy_key');
    config()->set('services.xendit.webhook_token', 'dummy_token');

    $this->variant = ProductVariant::create([
        'product_id'     => 1,
        'sku'            => 'SKU-SHIRT-01',
        'name'           => 'Cotton Shirt',
        'price'          => 100000.00,
        'stock_physical' => 10,
        'stock_reserved' => 0,
        'is_active'      => true,
    ]);
});

test('checkout creates Xendit invoice and populates payment record per FR-PAY-001', function () {
    Http::fake([
        'https://api.xendit.co/v2/invoices' => Http::response([
            'id'          => 'inv_test_9999',
            'external_id' => 'temp-id',
            'invoice_url' => 'https://checkout.xendit.co/web/inv_test_9999',
            'status'      => 'PENDING',
            'expiry_date' => '2026-11-07T10:15:00.000Z',
        ], 200),
    ]);

    $payload = [
        'idempotency_key'  => 'checkout-xendit-001',
        'guest_email'      => 'buyer@example.com',
        'guest_phone'      => '081234567890',
        'items'            => [
            ['product_variant_id' => $this->variant->id, 'quantity' => 1],
        ],
        'shipping_address' => [
            'recipient_name' => 'Buyer',
            'phone'          => '081234567890',
            'address_line1'  => 'Jl. Sudirman 1',
            'city'           => 'Jakarta',
            'province'       => 'DKI Jakarta',
            'postal_code'    => '10110',
        ],
    ];

    $response = $this->postJson('/api/checkout', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.payment_url', 'https://checkout.xendit.co/web/inv_test_9999');

    $orderId = $response->json('data.order_id');
    $order = Order::find($orderId);
    $payment = Payment::where('order_id', $orderId)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->xendit_invoice_id)->toBe('inv_test_9999')
        ->and($payment->xendit_payment_url)->toBe('https://checkout.xendit.co/web/inv_test_9999')
        ->and($order->expires_at)->not->toBeNull();

    $sessionAudit = AuditLog::where('order_id', $orderId)->where('event', 'payment.session_created')->first();
    expect($sessionAudit)->not->toBeNull()
        ->and($sessionAudit->payload['invoice_id'])->toBe('inv_test_9999');
});

test('checkout gracefully handles Xendit gateway outage per TBD-10 without rolling back order', function () {
    Http::fake([
        'https://api.xendit.co/v2/invoices' => Http::response([
            'error_code' => 'GATEWAY_TIMEOUT',
            'message'    => 'Connection timed out',
        ], 504),
    ]);

    $payload = [
        'idempotency_key'  => 'checkout-xendit-fail-001',
        'guest_email'      => 'buyer@example.com',
        'guest_phone'      => '081234567890',
        'items'            => [
            ['product_variant_id' => $this->variant->id, 'quantity' => 1],
        ],
        'shipping_address' => [
            'recipient_name' => 'Buyer',
            'phone'          => '081234567890',
            'address_line1'  => 'Jl. Sudirman 1',
            'city'           => 'Jakarta',
            'province'       => 'DKI Jakarta',
            'postal_code'    => '10110',
        ],
    ];

    $response = $this->postJson('/api/checkout', $payload);

    // Order is successfully created in pending_payment per TBD-10
    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.payment_url', null);

    $orderId = $response->json('data.order_id');
    $order = Order::find($orderId);
    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::PendingPayment);
});

test('retry payment session generates new link for guest with valid credentials', function () {
    $order = Order::create([
        'guest_email'     => 'guest@example.com',
        'guest_phone'     => '08123456789',
        'status'          => OrderStatus::PendingPayment,
        'subtotal'        => 100000.00,
        'shipping_cost'   => 0.00,
        'total'           => 100000.00,
        'idempotency_key' => 'id-retry-001',
    ]);

    Http::fake([
        'https://api.xendit.co/v2/invoices' => Http::response([
            'id'          => 'inv_retry_111',
            'invoice_url' => 'https://checkout.xendit.co/web/inv_retry_111',
            'expiry_date' => '2026-11-07T10:15:00.000Z',
        ], 200),
    ]);

    $response = $this->postJson("/api/orders/{$order->id}/payment-session", [
        'guest_email' => 'guest@example.com',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.payment_url', 'https://checkout.xendit.co/web/inv_retry_111');

    $payment = Payment::where('order_id', $order->id)->first();
    expect($payment->xendit_invoice_id)->toBe('inv_retry_111');
});

test('retry payment session rejects unauthorized guest email', function () {
    $order = Order::create([
        'guest_email'     => 'realguest@example.com',
        'status'          => OrderStatus::PendingPayment,
        'subtotal'        => 100000.00,
        'shipping_cost'   => 0.00,
        'total'           => 100000.00,
        'idempotency_key' => 'id-retry-002',
    ]);

    $response = $this->postJson("/api/orders/{$order->id}/payment-session", [
        'guest_email' => 'attacker@example.com',
    ]);

    $response->assertStatus(403);
});

test('retry payment session rejects orders that are not pending payment', function () {
    $order = Order::create([
        'guest_email'     => 'guest@example.com',
        'status'          => OrderStatus::Processing, // Already paid!
        'subtotal'        => 100000.00,
        'shipping_cost'   => 0.00,
        'total'           => 100000.00,
        'idempotency_key' => 'id-retry-003',
    ]);

    $response = $this->postJson("/api/orders/{$order->id}/payment-session", [
        'guest_email' => 'guest@example.com',
    ]);

    $response->assertStatus(422);
});

