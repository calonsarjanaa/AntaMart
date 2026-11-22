<?php

use App\Enums\CancellationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\AuditLog;
use App\Models\CancellationRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $product = Product::create(['id' => 81, 'name' => 'Cancellation Test Item', 'slug' => 'cancellation-test-item']);
    $this->variant = ProductVariant::create([
        'product_id'     => $product->id,
        'sku'            => 'CANCEL-01',
        'name'           => 'Cancellation Test Item',
        'price'          => 40000,
        'stock_physical' => 8,
        'stock_reserved' => 0,
        'is_active'      => true,
    ]);

    $this->owner = User::create([
        'name' => 'Store Owner', 'email' => 'owner-cancel@example.com', 'password' => bcrypt('password'), 'role' => 'store_owner',
    ]);
    $this->customer = User::create([
        'name' => 'Customer', 'email' => 'customer-cancel@example.com', 'password' => bcrypt('password'), 'role' => 'customer',
    ]);
    $this->otherCustomer = User::create([
        'name' => 'Other Customer', 'email' => 'other-cancel@example.com', 'password' => bcrypt('password'), 'role' => 'customer',
    ]);

    $this->makeOrder = function (string $suffix, ?int $customerId = null): Order {
        return Order::create([
            'customer_id'     => $customerId,
            'guest_email'     => $customerId ? null : 'guest-cancel@example.com',
            'guest_phone'     => $customerId ? null : '081234567890',
            'status'          => OrderStatus::Processing,
            'subtotal'        => 80000,
            'shipping_cost'   => 0,
            'total'           => 80000,
            'idempotency_key' => "cancel-{$suffix}",
        ]);
    };

    $this->makePaidPayment = function (Order $order): Payment {
        return Payment::create([
            'order_id' => $order->id,
            'amount'   => $order->total,
            'status'   => PaymentStatus::Paid,
            'paid_at'  => now(),
        ]);
    };
});

test('guest can submit one cancellation request using the order email', function () {
    $order = ($this->makeOrder)('guest-request');

    $response = $this->postJson("/api/orders/{$order->id}/cancellation-requests", [
        'guest_email' => 'GUEST-CANCEL@example.com',
        'reason'      => 'Ordered the wrong size.',
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'pending');
    expect(CancellationRequest::where('order_id', $order->id)->count())->toBe(1)
        ->and(AuditLog::where('order_id', $order->id)->where('event', 'cancellation.requested')->count())->toBe(1);

    $this->postJson("/api/orders/{$order->id}/cancellation-requests", [
        'guest_email' => 'guest-cancel@example.com',
        'reason'      => 'Duplicate request.',
    ])->assertUnprocessable();

    expect(CancellationRequest::where('order_id', $order->id)->count())->toBe(1);
});

test('guest with wrong email cannot request cancellation and completed orders reject requests', function () {
    $order = ($this->makeOrder)('guest-invalid');

    $this->postJson("/api/orders/{$order->id}/cancellation-requests", [
        'guest_email' => 'intruder@example.com',
        'reason'      => 'Please cancel.',
    ])->assertForbidden();

    $order->update(['status' => OrderStatus::Completed]);
    $this->postJson("/api/orders/{$order->id}/cancellation-requests", [
        'guest_email' => 'guest-cancel@example.com',
        'reason'      => 'Please cancel.',
    ])->assertUnprocessable();

    expect(CancellationRequest::where('order_id', $order->id)->count())->toBe(0);
});

test('authenticated customers can request cancellation only for their own orders', function () {
    $ownedOrder = ($this->makeOrder)('customer-owned', $this->customer->id);
    $foreignOrder = ($this->makeOrder)('customer-foreign', $this->otherCustomer->id);

    $this->actingAs($this->customer)->postJson("/api/orders/{$ownedOrder->id}/cancellation-requests", [
        'reason' => 'Changed my mind.',
    ])->assertCreated();

    $this->actingAs($this->customer)->postJson("/api/orders/{$foreignOrder->id}/cancellation-requests", [
        'reason' => 'Attempt to cancel another customer order.',
    ])->assertForbidden();
});

test('guest can withdraw a pending cancellation request but cannot withdraw it twice', function () {
    $order = ($this->makeOrder)('withdraw');
    $request = $order->cancellationRequests()->create([
        'reason' => 'Wrong item.',
        'status' => CancellationStatus::Pending,
    ]);

    $this->postJson("/api/orders/{$order->id}/cancellation-requests/{$request->id}/withdraw", [
        'guest_email' => 'guest-cancel@example.com',
    ])->assertOk()->assertJsonPath('data.status', 'withdrawn');

    $this->postJson("/api/orders/{$order->id}/cancellation-requests/{$request->id}/withdraw", [
        'guest_email' => 'guest-cancel@example.com',
    ])->assertUnprocessable();

    expect(AuditLog::where('order_id', $order->id)->where('event', 'cancellation.withdrawn')->count())->toBe(1);
});

test('guest cannot withdraw a cancellation request after the Store Owner has decided it', function () {
    $order = ($this->makeOrder)('decided-withdrawal');
    $request = $order->cancellationRequests()->create([
        'reason'     => 'Please cancel.',
        'status'     => CancellationStatus::Approved,
        'decided_by' => $this->owner->id,
        'decided_at' => now(),
    ]);

    $this->postJson("/api/orders/{$order->id}/cancellation-requests/{$request->id}/withdraw", [
        'guest_email' => 'guest-cancel@example.com',
    ])->assertUnprocessable();

    expect($request->fresh()->status)->toBe(CancellationStatus::Approved);
});

test('store owner approval cancels paid order and flags refund without restocking', function () {
    $order = ($this->makeOrder)('approve');
    ($this->makePaidPayment)($order);
    $this->variant->update(['stock_physical' => 6]);
    $request = $order->cancellationRequests()->create([
        'requested_by' => $this->customer->id,
        'reason'       => 'Item arrived damaged.',
        'status'       => CancellationStatus::Pending,
    ]);

    $this->actingAs($this->owner)->postJson("/api/admin/orders/{$order->id}/cancellation-requests/{$request->id}/review", [
        'decision' => 'approve',
    ])->assertOk()->assertJsonPath('data.status', 'approved');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Refund::where('order_id', $order->id)->first()->status)->toBe(RefundStatus::ManualRefundPending)
        ->and($this->variant->fresh()->stock_physical)->toBe(6)
        ->and(AuditLog::where('order_id', $order->id)->where('event', 'refund.flagged')->count())->toBe(1)
        ->and(AuditLog::where('order_id', $order->id)->where('event', 'cancellation.approved')->count())->toBe(1);
});

test('store owner rejection requires a reason and leaves the order processing', function () {
    $order = ($this->makeOrder)('reject');
    $request = $order->cancellationRequests()->create([
        'requested_by' => $this->customer->id,
        'reason'       => 'No longer needed.',
        'status'       => CancellationStatus::Pending,
    ]);
    $url = "/api/admin/orders/{$order->id}/cancellation-requests/{$request->id}/review";

    $this->actingAs($this->owner)->postJson($url, ['decision' => 'reject'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rejection_reason']);

    $this->actingAs($this->owner)->postJson($url, [
        'decision'         => 'reject',
        'rejection_reason' => 'The order has already been packed.',
    ])->assertOk()->assertJsonPath('data.status', 'rejected');

    expect($order->fresh()->status)->toBe(OrderStatus::Processing)
        ->and($request->fresh()->rejection_reason)->toBe('The order has already been packed.')
        ->and(AuditLog::where('order_id', $order->id)->where('event', 'cancellation.rejected')->count())->toBe(1);
});

test('customer cannot use owner cancellation endpoints and owner can cancel processing order directly', function () {
    $order = ($this->makeOrder)('owner-cancel');
    ($this->makePaidPayment)($order);
    $pendingRequest = $order->cancellationRequests()->create([
        'requested_by' => $this->customer->id,
        'reason'       => 'Please cancel this order.',
        'status'       => CancellationStatus::Pending,
    ]);

    $this->actingAs($this->customer)->postJson("/api/admin/orders/{$order->id}/cancel", [
        'reason' => 'Not authorized.',
    ])->assertForbidden();

    $this->actingAs($this->owner)->postJson("/api/admin/orders/{$order->id}/cancel", [
        'reason' => 'Inventory unavailable for fulfillment.',
    ])->assertOk()->assertJsonPath('data.status', 'approved');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->refund->status)->toBe(RefundStatus::ManualRefundPending)
        ->and($pendingRequest->fresh()->status)->toBe(CancellationStatus::Approved)
        ->and(AuditLog::where('order_id', $order->id)->where('event', 'cancellation.approved')->count())->toBe(2);
});

test('customer can directly cancel awaiting_confirmation and pending_payment orders without owner approval', function () {
    $order = ($this->makeOrder)('direct-cancel-pending', $this->customer->id);
    $order->update(['status' => OrderStatus::PendingPayment, 'expires_at' => now()->addHour()]);
    Payment::create([
        'order_id' => $order->id,
        'amount'   => $order->total,
        'status'   => PaymentStatus::Pending,
    ]);
    $this->variant->update(['stock_reserved' => 2]);
    \App\Models\InventoryReservation::create([
        'order_id'           => $order->id,
        'product_variant_id' => $this->variant->id,
        'quantity'           => 2,
        'status'             => 'reserved',
        'reserved_at'        => now(),
    ]);

    $this->actingAs($this->customer)
        ->postJson("/api/orders/{$order->id}/cancellation-requests", [
            'reason' => 'Ingin ganti metode bayar.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'approved');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->payment->status)->toBe(PaymentStatus::Expired)
        ->and($this->variant->fresh()->stock_reserved)->toBe(0);
});

test('customer can request cancellation on shipped order up to maximum 3 attempts', function () {
    $order = ($this->makeOrder)('shipped-max-3', $this->customer->id);
    $order->update(['status' => OrderStatus::Shipped]);
    ($this->makePaidPayment)($order);

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $reqResponse = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/cancellation-requests", [
                'reason' => "Pengajuan batal ke-{$attempt}",
            ])
            ->assertCreated();

        $reqId = $reqResponse->json('data.id');

        // Owner rejects each attempt
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/admin/orders/{$order->id}/cancellation-requests/{$reqId}/review", [
                'decision'         => 'reject',
                'rejection_reason' => "Ditolak ke-{$attempt}",
            ])
            ->assertOk();
    }

    // 4th attempt must be blocked
    $this->actingAs($this->customer, 'sanctum')
        ->postJson("/api/orders/{$order->id}/cancellation-requests", [
            'reason' => 'Pengajuan ke-4 harus ditolak.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cancellation_request']);

    expect(CancellationRequest::where('order_id', $order->id)->count())->toBe(3);
});

test('owner cancelling pending_payment locks payment button and shows alert when customer hits stale pay URL', function () {
    \Illuminate\Support\Facades\Http::fake([
        'https://api.xendit.co/v2/invoices/*' => \Illuminate\Support\Facades\Http::response([
            'id' => 'inv_lock_test',
            'status' => 'PENDING',
            'amount' => 80000,
        ], 200),
        'https://api.xendit.co/invoices/inv_lock_test/expire!' => \Illuminate\Support\Facades\Http::response([
            'id' => 'inv_lock_test',
            'status' => 'EXPIRED',
        ], 200),
    ]);

    $order = ($this->makeOrder)('owner-cancel-pending');
    $order->update(['status' => OrderStatus::PendingPayment, 'expires_at' => now()->addHour()]);
    Payment::create([
        'order_id'           => $order->id,
        'amount'             => $order->total,
        'status'             => PaymentStatus::Pending,
        'xendit_invoice_id'  => 'inv_lock_test',
        'xendit_payment_url' => 'https://checkout.xendit.co/web/inv_lock_test',
    ]);
    $this->variant->update(['stock_reserved' => 1]);
    \App\Models\InventoryReservation::create([
        'order_id'           => $order->id,
        'product_variant_id' => $this->variant->id,
        'quantity'           => 1,
        'status'             => 'reserved',
        'reserved_at'        => now(),
    ]);

    // Customer already had the signed pay URL open before owner cancelled
    $stalePayUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'storefront.checkout.result.pay',
        now()->addDay(),
        ['orderId' => $order->id]
    );

    // Owner cancels pending_payment order with reason
    $this->actingAs($this->owner)
        ->postJson("/api/admin/orders/{$order->id}/cancel", [
            'reason' => 'Stok rusak saat pengecekan ulang.',
        ])
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($this->variant->fresh()->stock_reserved)->toBe(0);

    // Customer clicks stale "Bayar Pesanan" button -> blocked with alert, NOT redirected to Xendit!
    $payAttempt = $this->get($stalePayUrl);
    $payAttempt->assertRedirect()
        ->assertSessionHasErrors('payment');
    expect($payAttempt->headers->get('Location'))->not->toContain('checkout.xendit.co');
});

test('owner can cancel shipped paid order and cannot ship while pending cancellation request exists', function () {
    $order = ($this->makeOrder)('ship-blocked-by-cancel', $this->customer->id);
    ($this->makePaidPayment)($order);

    $req = $order->cancellationRequests()->create([
        'requested_by' => $this->customer->id,
        'reason'       => 'Salah alamat',
        'status'       => CancellationStatus::Pending,
    ]);

    // Attempting to ship while pending cancellation exists is blocked
    $this->actingAs($this->owner)
        ->postJson("/api/admin/orders/{$order->id}/shipment", [
            'tracking_number' => 'JNE-BLOCKED',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['order']);

    // Owner rejects cancellation -> now shipping succeeds
    $this->actingAs($this->owner)
        ->postJson("/api/admin/orders/{$order->id}/cancellation-requests/{$req->id}/review", [
            'decision'         => 'reject',
            'rejection_reason' => 'Sudah dikemas',
        ])
        ->assertOk();

    $this->actingAs($this->owner)
        ->postJson("/api/admin/orders/{$order->id}/shipment", [
            'tracking_number' => 'JNE-OK-001',
        ])
        ->assertCreated();

    // Owner can still cancel shipped paid order
    $this->actingAs($this->owner)
        ->postJson("/api/admin/orders/{$order->id}/cancel", [
            'reason' => 'Kurir gagal kirim dan barang diretur.',
        ])
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Refund::where('order_id', $order->id)->first()->status)->toBe(RefundStatus::ManualRefundPending);
});
