<?php

use App\Enums\CancellationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\AuditLog;
use App\Models\CancellationRequest;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config()->set('services.xendit.secret_key', 'xnd_test_ui_key');

    $this->product = Product::create([
        'name' => 'UI Test Mug', 'slug' => 'ui-test-mug', 'description' => 'A test product.', 'is_active' => true,
    ]);
    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id, 'sku' => 'UI-MUG-01', 'name' => 'White',
        'price' => 25000, 'stock_physical' => 8, 'stock_reserved' => 0, 'is_active' => true,
    ]);
    $this->owner = User::create([
        'name' => 'UI Owner', 'email' => 'ui-owner@example.test', 'password' => bcrypt('password'), 'role' => 'store_owner',
    ]);
    $this->customer = User::create([
        'name' => 'UI Customer', 'email' => 'ui-customer@example.test', 'password' => bcrypt('password'), 'role' => 'customer',
    ]);
    $this->makeOrder = fn (string $key, OrderStatus $status = OrderStatus::Processing, ?int $customerId = null): Order => Order::create([
        'customer_id' => $customerId,
        'guest_email' => $customerId ? null : 'ui-guest@example.test',
        'guest_phone' => $customerId ? null : '081234567890',
        'status' => $status,
        'subtotal' => 25000,
        'shipping_cost' => 0,
        'total' => 25000,
        'idempotency_key' => $key,
    ]);
});

test('guest can browse, add to cart, check out, hand off payment, and track the order', function () {
    Http::fake([
        'https://api.xendit.co/v2/invoices' => Http::response([
            'id' => 'inv_ui_test',
            'invoice_url' => 'https://checkout.xendit.test/inv_ui_test',
            'expiry_date' => now()->addMinutes(15)->toIso8601String(),
        ]),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('lang="id"', false)
        ->assertSee('Lewati ke konten')
        ->assertSee('UI Test Mug');
    $this->get('/products/ui-test-mug')
        ->assertOk()
        ->assertSee('<fieldset class="variant-options" aria-labelledby="variant-list-title">', false)
        ->assertSee('Pilihan produk');

    $this->post('/cart/items', ['product_variant_id' => $this->variant->id, 'quantity' => 1])
        ->assertRedirect(route('storefront.cart.index'));
    $this->get('/cart')->assertOk()->assertSee('UI Test Mug')->assertSee('Lanjut ke checkout');
    $this->get('/checkout')->assertOk()->assertSee('Alamat pengiriman')->assertSee('Kontak pembeli');

    $this->post('/checkout', [
        'guest_email' => 'browser-buyer@example.test',
        'guest_phone' => '081234567890',
        'payment_method' => 'xendit',
        'shipping_address' => [
            'recipient_name' => 'Browser Buyer',
            'phone' => '081234567890',
            'address_line1' => 'Jl. UI 1',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '10110',
        ],
    ])->assertRedirect('https://checkout.xendit.test/inv_ui_test');

    $order = Order::where('guest_email', 'browser-buyer@example.test')->firstOrFail();
    expect($order->items)->toHaveCount(1)
        ->and($order->payment->xendit_invoice_id)->toBe('inv_ui_test');

    $this->post('/orders/track', ['order_id' => $order->id, 'email' => 'browser-buyer@example.test'])
        ->assertRedirect(route('storefront.orders.track'));
    $this->get('/orders/track')->assertOk()->assertSee($order->id)->assertSee('UI Test Mug');
});

test('owner browser actions review cancellations record refunds and complete shipments', function () {
    $cancelOrder = ($this->makeOrder)('ui-cancel-order');
    Payment::create([
        'order_id' => $cancelOrder->id, 'amount' => $cancelOrder->total,
        'status' => PaymentStatus::Paid, 'paid_at' => now(),
    ]);
    $cancelItem = OrderItem::create([
        'order_id' => $cancelOrder->id, 'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'product_snapshot' => ['name' => 'UI Test Mug'], 'variant_snapshot' => ['name' => 'White'],
        'sku' => 'UI-MUG-01', 'quantity' => 1, 'unit_price' => 25000, 'subtotal' => 25000,
    ]);
    InventoryReservation::create([
        'order_id' => $cancelOrder->id, 'product_variant_id' => $this->variant->id,
        'quantity' => 1, 'status' => 'converted', 'reserved_at' => now()->subHour(),
    ]);
    $request = CancellationRequest::create([
        'order_id' => $cancelOrder->id, 'reason' => 'Salah memilih barang.', 'status' => CancellationStatus::Pending,
    ]);
    AuditLog::create([
        'order_id' => $cancelOrder->id, 'actor_type' => 'system', 'event' => 'payment.confirmed',
        'payload' => ['amount' => 25000, 'payment_id' => 'PRIVATE-PAYMENT-ID', 'callback_secret' => 'PRIVATE-CALLBACK-SECRET'],
    ]);

    $this->actingAs($this->owner)->get('/owner/cancellations')
        ->assertOk()->assertSee('Salah memilih barang.');
    $this->get(route('owner.orders.show', $cancelOrder->id))
        ->assertOk()
        ->assertSee('Pembayaran dikonfirmasi')
        ->assertSee('Nominal Rp 25.000')
        ->assertDontSee('PRIVATE-PAYMENT-ID')
        ->assertDontSee('PRIVATE-CALLBACK-SECRET');

    $this->post(route('owner.orders.cancellations.review', [$cancelOrder->id, $request->id]), [
        'decision' => 'approve',
    ])->assertRedirect(route('owner.orders.show', $cancelOrder->id));
    expect($cancelOrder->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Refund::where('order_id', $cancelOrder->id)->first()->status)->toBe(RefundStatus::ManualRefundPending);

    $this->post(route('owner.orders.refund.mark-refunded', $cancelOrder->id), [
        'notes' => 'Transfer manual selesai.',
        'resellable_item_ids' => [$cancelItem->id],
    ])->assertRedirect(route('owner.orders.show', $cancelOrder->id));
    expect(Refund::where('order_id', $cancelOrder->id)->first()->status)->toBe(RefundStatus::Refunded)
        ->and($this->variant->fresh()->stock_physical)->toBe(9);

    $shipmentOrder = ($this->makeOrder)('ui-shipment-order');
    $this->post(route('owner.orders.shipment.store', $shipmentOrder->id), [
        'tracking_number' => 'UI-TRACK-001',
        'tracking_url' => 'https://tracking.example.test/UI-TRACK-001',
    ])->assertRedirect(route('owner.orders.show', $shipmentOrder->id));
    expect($shipmentOrder->fresh()->status)->toBe(OrderStatus::Shipped)
        ->and(Shipment::where('order_id', $shipmentOrder->id)->first()->tracking_number)->toBe('UI-TRACK-001');

    $this->post(route('owner.orders.delivery.confirm', $shipmentOrder->id))
        ->assertRedirect(route('owner.orders.show', $shipmentOrder->id));
    expect($shipmentOrder->fresh()->status)->toBe(OrderStatus::Completed)
        ->and(Shipment::where('order_id', $shipmentOrder->id)->first()->delivered_at)->not->toBeNull();
});

test('guest can request cancellation from the verified tracking page', function () {
    $order = ($this->makeOrder)('ui-guest-cancel');

    $this->post('/orders/track', [
        'order_id' => $order->id,
        'email' => $order->guest_email,
    ])->assertRedirect(route('storefront.orders.track'));
    $this->get('/orders/track')->assertOk()->assertSee('Permintaan pembatalan');

    $signedUrl = URL::temporarySignedRoute(
        'storefront.orders.cancellation.store',
        now()->addDay(),
        ['id' => $order->id]
    );
    $this->post($signedUrl, [
        'guest_email' => $order->guest_email,
        'reason' => 'Saya memilih barang yang keliru.',
    ])->assertRedirect(route('storefront.orders.track'));

    expect($order->cancellationRequests()->first()->status)->toBe(CancellationStatus::Pending);
});

test('empty and invalid storefront states are explained and customer order access remains isolated', function () {
    $this->get('/cart')->assertOk()->assertSee('Keranjang masih kosong');
    $this->get('/checkout')->assertRedirect(route('storefront.cart.index'));

    $this->post('/cart/items', ['product_variant_id' => $this->variant->id, 'quantity' => 99])
        ->assertSessionHasErrors('quantity');
    $this->get('/cart')->assertOk()->assertSee('Stok tersedia');

    $otherCustomer = User::create([
        'name' => 'Other UI Customer', 'email' => 'other-ui-customer@example.test',
        'password' => bcrypt('password'), 'role' => 'customer',
    ]);
    $foreignOrder = ($this->makeOrder)('ui-foreign-order', OrderStatus::Processing, $otherCustomer->id);
    $this->actingAs($this->customer)
        ->get(route('storefront.account.orders.show', $foreignOrder->id))
        ->assertNotFound();
    $this->get('/owner/orders')->assertForbidden();

    auth()->logout();
    $this->get('/owner/orders')->assertRedirect('/owner/login');
});

test('owner login shows the rate limit response after repeated invalid attempts', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/owner/login', [
            'email' => 'unknown-owner@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post('/owner/login', [
        'email' => 'unknown-owner@example.test',
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});
