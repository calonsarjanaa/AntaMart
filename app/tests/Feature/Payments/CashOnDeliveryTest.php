<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->product = Product::create([
        'name' => 'COD Mug', 'slug' => 'cod-mug', 'is_active' => true,
    ]);
    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id, 'sku' => 'COD-MUG-01', 'name' => 'Default',
        'price' => 40000, 'stock_physical' => 5, 'stock_reserved' => 0, 'is_active' => true,
    ]);
    $this->owner = User::create([
        'name' => 'COD Owner', 'email' => 'cod-owner@example.test', 'password' => bcrypt('password'), 'role' => 'store_owner',
    ]);
});

test('API COD checkout commits inventory without creating a Xendit invoice', function () {
    Http::fake();
    $response = $this->postJson('/api/checkout', [
        'idempotency_key' => 'cod-api-checkout',
        'guest_email' => 'cod-buyer@example.test',
        'guest_phone' => '081234567890',
        'payment_method' => 'cod',
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
        'shipping_address' => [
            'recipient_name' => 'COD Buyer', 'phone' => '081234567890',
            'address_line1' => 'Jl. COD 1', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'postal_code' => '10110',
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.payment_method', 'cod')
        ->assertJsonPath('data.payment_status', 'pending')
        ->assertJsonPath('data.payment_url', null)
        ->assertJsonPath('data.expires_at', null);

    $order = Order::findOrFail($response->json('data.order_id'));
    expect($order->payment->payment_method)->toBe(PaymentMethod::CashOnDelivery)
        ->and($order->payment->status)->toBe(PaymentStatus::Pending)
        ->and($order->expires_at)->toBeNull()
        ->and($this->variant->fresh()->stock_physical)->toBe(3)
        ->and($this->variant->fresh()->stock_reserved)->toBe(0)
        ->and(InventoryReservation::where('order_id', $order->id)->value('status'))->toBe('converted')
        ->and(AuditLog::where('order_id', $order->id)->where('event', 'payment.cod_order_created')->exists())->toBeTrue();
    Http::assertNothingSent();

    $this->actingAs($this->owner)->postJson('/api/admin/orders/'.$order->id.'/cancel', ['reason' => 'Customer requested cancellation.'])
        ->assertOk();
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Refund::where('order_id', $order->id)->exists())->toBeFalse()
        ->and($this->variant->fresh()->stock_physical)->toBe(5)
        ->and(InventoryReservation::where('order_id', $order->id)->value('status'))->toBe('restocked')
        ->and(AuditLog::where('order_id', $order->id)->where('event', 'stock.restocked_after_cod_cancellation')->exists())->toBeTrue();
});

test('owner must confirm COD cash collection when confirming delivery', function () {
    $checkout = $this->postJson('/api/checkout', [
        'idempotency_key' => 'cod-delivery-checkout',
        'guest_email' => 'cod-delivery@example.test',
        'guest_phone' => '081234567890',
        'payment_method' => 'cod',
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
        'shipping_address' => [
            'recipient_name' => 'COD Delivery', 'phone' => '081234567890',
            'address_line1' => 'Jl. COD 2', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'postal_code' => '10110',
        ],
    ])->assertCreated();
    $orderId = $checkout->json('data.order_id');

    $this->actingAs($this->owner)->postJson("/api/admin/orders/{$orderId}/shipment", [
        'tracking_number' => 'COD-TRACK-001',
    ])->assertCreated();

    $this->postJson("/api/admin/orders/{$orderId}/shipment/confirm-delivery", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cash_received']);
    expect(Order::findOrFail($orderId)->status)->toBe(OrderStatus::Shipped)
        ->and(Payment::where('order_id', $orderId)->first()->status)->toBe(PaymentStatus::Pending)
        ->and(Shipment::where('order_id', $orderId)->first()->delivered_at)->toBeNull();

    $this->postJson("/api/admin/orders/{$orderId}/shipment/confirm-delivery", ['cash_received' => true])
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'paid');
    expect(Order::findOrFail($orderId)->status)->toBe(OrderStatus::Completed)
        ->and(Payment::where('order_id', $orderId)->first()->status)->toBe(PaymentStatus::Paid)
        ->and(Payment::where('order_id', $orderId)->first()->paid_at)->not->toBeNull()
        ->and(AuditLog::where('order_id', $orderId)->where('event', 'payment.cod_collected')->exists())->toBeTrue();
});

test('owner browser delivery form requires COD cash confirmation', function () {
    $checkout = $this->postJson('/api/checkout', [
        'idempotency_key' => 'cod-browser-delivery-checkout',
        'guest_email' => 'cod-browser-delivery@example.test',
        'guest_phone' => '081234567890',
        'payment_method' => 'cod',
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
        'shipping_address' => [
            'recipient_name' => 'COD Browser Delivery', 'phone' => '081234567890',
            'address_line1' => 'Jl. COD 4', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'postal_code' => '10110',
        ],
    ])->assertCreated();
    $orderId = $checkout->json('data.order_id');

    $this->actingAs($this->owner)->post(route('owner.orders.shipment.store', $orderId), [
        'tracking_number' => 'COD-WEB-001',
    ])->assertRedirect(route('owner.orders.show', $orderId));
    $this->get(route('owner.orders.show', $orderId))
        ->assertOk()
        ->assertSee('Saya mengonfirmasi uang COD sebesar')
        ->assertSee('name="cash_received"', false);

    $this->post(route('owner.orders.delivery.confirm', $orderId))
        ->assertRedirect(route('owner.orders.show', $orderId))
        ->assertSessionHasErrors('cash_received');
    expect(Order::findOrFail($orderId)->status)->toBe(OrderStatus::Shipped)
        ->and(Payment::where('order_id', $orderId)->first()->status)->toBe(PaymentStatus::Pending);

    $this->post(route('owner.orders.delivery.confirm', $orderId), ['cash_received' => '1'])
        ->assertRedirect(route('owner.orders.show', $orderId));
    expect(Order::findOrFail($orderId)->status)->toBe(OrderStatus::Completed)
        ->and(Payment::where('order_id', $orderId)->first()->status)->toBe(PaymentStatus::Paid);
});

test('browser checkout offers COD and explains payment due on delivery', function () {
    Http::fake();
    $this->post('/cart/items', ['product_variant_id' => $this->variant->id, 'quantity' => 1]);
    $this->get('/checkout')->assertOk()->assertSee('Bayar di tempat (COD)');

    $response = $this->post('/checkout', [
        'guest_email' => 'cod-browser@example.test',
        'guest_phone' => '081234567890',
        'payment_method' => 'cod',
        'shipping_address' => [
            'recipient_name' => 'COD Browser', 'phone' => '081234567890',
            'address_line1' => 'Jl. COD 3', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'postal_code' => '10110',
        ],
    ])->assertRedirect();

    $order = Order::where('guest_email', 'cod-browser@example.test')->firstOrFail();
    expect($order->status)->toBe(OrderStatus::Processing)
        ->and($order->payment->payment_method)->toBe(PaymentMethod::CashOnDelivery);
    $this->get($response->headers->get('Location'))
        ->assertOk()
        ->assertSee('Pesanan COD sedang diproses')
        ->assertSee('saat pesanan diterima');
    Http::assertNothingSent();
});

