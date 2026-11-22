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
    $this->get('/checkout')
        ->assertOk()
        ->assertSee('Alamat pengiriman')
        ->assertSee('Kontak pembeli')
        ->assertSee('Gunakan informasi checkout sebelumnya');

    $checkoutResponse = $this->post('/checkout', [
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
    ]);

    $order = Order::where('guest_email', 'browser-buyer@example.test')->firstOrFail();
    expect($order->items)->toHaveCount(1)
        ->and($order->status)->toBe(OrderStatus::AwaitingConfirmation)
        ->and($order->payment->xendit_invoice_id)->toBeNull();

    $checkoutResponse->assertRedirect();
    $resultLocation = $checkoutResponse->headers->get('Location');
    expect($resultLocation)->toContain("/checkout/result/{$order->id}");

    $this->get($resultLocation)
        ->assertOk()
        ->assertSee($order->id)
        ->assertSee('Menunggu konfirmasi toko');

    // Owner accepts order -> Xendit QRIS invoice created -> customer sees "Bayar Pesanan"
    $this->actingAs($this->owner)
        ->post(route('owner.orders.accept', $order->id))
        ->assertRedirect(route('owner.orders.show', $order->id));

    auth()->logout();

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->fresh()->payment->xendit_invoice_id)->toBe('inv_ui_test');

    $this->get($resultLocation)
        ->assertOk()
        ->assertSee($order->id)
        ->assertSee('Bayar Pesanan')
        ->assertSee('https://checkout.xendit.test/inv_ui_test', false);

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

test('customer can cancel awaiting confirmation order and recover from xendit failure after owner accepts', function () {
    Http::fake([
        'https://api.xendit.co/v2/invoices' => Http::response(['message' => 'Gateway error'], 500),
    ]);

    // Put item in cart
    $this->post('/cart/items', ['product_variant_id' => $this->variant->id, 'quantity' => 1]);

    // Checkout with Xendit -> enters awaiting_confirmation
    $response = $this->post('/checkout', [
        'guest_email' => 'failed-payment@example.test',
        'guest_phone' => '081234567890',
        'payment_method' => 'xendit',
        'shipping_address' => [
            'recipient_name' => 'Failed Buyer',
            'phone' => '081234567890',
            'address_line1' => 'Jl. Gagal 1',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '10110',
        ],
    ]);

    $response->assertRedirect();
    $order = Order::where('guest_email', 'failed-payment@example.test')->firstOrFail();
    expect($order->status)->toBe(OrderStatus::AwaitingConfirmation)
        ->and($this->variant->fresh()->stock_reserved)->toBe(1);

    // Cancel while awaiting confirmation -> releases stock and restores cart
    $cancelUrl = URL::temporarySignedRoute('storefront.checkout.result.cancel', now()->addDay(), ['orderId' => $order->id]);
    $this->post($cancelUrl)->assertRedirect(route('storefront.cart.index'));

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($this->variant->fresh()->stock_reserved)->toBe(0)
        ->and(session('cart'))->toBe([$this->variant->id => 1]);

    // Checkout again, owner accepts (Xendit fails -> stays pending_payment without URL), then switch to COD
    $this->post('/checkout', [
        'guest_email' => 'failed-payment2@example.test',
        'guest_phone' => '081234567890',
        'payment_method' => 'xendit',
        'shipping_address' => [
            'recipient_name' => 'Failed Buyer',
            'phone' => '081234567890',
            'address_line1' => 'Jl. Gagal 1',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '10110',
        ],
    ])->assertRedirect();

    $secondOrder = Order::where('guest_email', 'failed-payment2@example.test')->firstOrFail();
    $this->actingAs($this->owner)->post(route('owner.orders.accept', $secondOrder->id))
        ->assertRedirect(route('owner.orders.show', $secondOrder->id));
    auth()->logout();

    expect($secondOrder->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($secondOrder->fresh()->payment->xendit_payment_url)->toBeNull();

    $switchCodUrl = URL::temporarySignedRoute('storefront.checkout.result.switch-cod', now()->addDay(), ['orderId' => $secondOrder->id]);
    $this->post($switchCodUrl)->assertRedirect();

    expect($secondOrder->fresh()->status)->toBe(OrderStatus::Processing)
        ->and($secondOrder->fresh()->payment->payment_method)->toBe(\App\Enums\PaymentMethod::CashOnDelivery)
        ->and($this->variant->fresh()->stock_reserved)->toBe(0)
        ->and($this->variant->fresh()->stock_physical)->toBe(7);
});

test('checkout page renders toggle switch with previous checkout address for returning customer', function () {
    $prevOrder = ($this->makeOrder)('prev-checkout-order', OrderStatus::Completed, $this->customer->id);
    \App\Models\OrderAddress::create([
        'order_id' => $prevOrder->id,
        'recipient_name' => 'UI Customer Old',
        'phone' => '0899999999',
        'address_line1' => 'Jl. Kebon Jeruk No. 5',
        'city' => 'Jakarta Barat',
        'province' => 'DKI Jakarta',
        'postal_code' => '11530',
        'country' => 'ID',
    ]);

    $this->actingAs($this->customer)
        ->post('/cart/items', ['product_variant_id' => $this->variant->id, 'quantity' => 1]);

    $this->actingAs($this->customer)
        ->get('/checkout')
        ->assertOk()
        ->assertSee('Gunakan informasi checkout sebelumnya')
        ->assertSee('Jl. Kebon Jeruk No. 5')
        ->assertSee('11530');
});

test('customer can switch to COD or cancel pending xendit order directly from checkout result page', function () {
    Http::fake([
        'https://api.xendit.co/v2/invoices/*' => Http::response([
            'id' => 'inv_pending',
            'status' => 'PENDING',
            'amount' => 25000,
        ], 200),
    ]);

    // 1. Create pending Xendit order and cancel from result page
    $order1 = ($this->makeOrder)('result-cancel-key', OrderStatus::PendingPayment);
    $order1->update(['expires_at' => now()->addMinutes(10)]);
    Payment::create([
        'order_id' => $order1->id,
        'payment_method' => \App\Enums\PaymentMethod::Xendit,
        'amount' => 25000,
        'status' => PaymentStatus::Pending,
        'xendit_invoice_id' => 'inv_result_cancel_1',
        'xendit_payment_url' => 'https://checkout.xendit.co/web/inv_result_cancel_1',
        'expired_at' => now()->addMinutes(10),
    ]);
    OrderItem::create([
        'order_id' => $order1->id,
        'product_id' => $this->variant->product_id,
        'product_variant_id' => $this->variant->id,
        'product_snapshot' => ['name' => 'UI Test Mug'],
        'variant_snapshot' => ['name' => 'Default'],
        'sku' => $this->variant->sku,
        'quantity' => 1,
        'unit_price' => 25000,
        'subtotal' => 25000,
    ]);
    $this->variant->update(['stock_reserved' => 1]);
    \App\Models\InventoryReservation::create([
        'order_id' => $order1->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'status' => 'reserved',
        'reserved_at' => now(),
    ]);

    $resultUrl1 = URL::temporarySignedRoute('storefront.checkout.result', now()->addDay(), ['orderId' => $order1->id]);
    $this->get($resultUrl1)
        ->assertOk()
        ->assertSee('Beralih ke Bayar di Tempat (COD)')
        ->assertSee('Batalkan Pesanan');

    $cancelUrl1 = URL::temporarySignedRoute('storefront.checkout.result.cancel', now()->addDay(), ['orderId' => $order1->id]);
    $this->post($cancelUrl1)->assertRedirect(route('storefront.cart.index'));

    expect($order1->fresh()->status)->toBe(OrderStatus::Expired)
        ->and($this->variant->fresh()->stock_reserved)->toBe(0)
        ->and(session('cart'))->toBe([$this->variant->id => 1]);

    // 2. Create pending Xendit order and switch to COD from result page
    $order2 = ($this->makeOrder)('result-cod-key', OrderStatus::PendingPayment);
    $order2->update(['expires_at' => now()->addMinutes(10)]);
    Payment::create([
        'order_id' => $order2->id,
        'payment_method' => \App\Enums\PaymentMethod::Xendit,
        'amount' => 25000,
        'status' => PaymentStatus::Pending,
        'xendit_invoice_id' => 'inv_result_cod_2',
        'xendit_payment_url' => 'https://checkout.xendit.co/web/inv_result_cod_2',
        'expired_at' => now()->addMinutes(10),
    ]);
    $this->variant->refresh()->update(['stock_reserved' => 1]);
    \App\Models\InventoryReservation::create([
        'order_id' => $order2->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'status' => 'reserved',
        'reserved_at' => now(),
    ]);

    $switchCodUrl2 = URL::temporarySignedRoute('storefront.checkout.result.switch-cod', now()->addDay(), ['orderId' => $order2->id]);
    $this->post($switchCodUrl2)->assertRedirect();

    expect($order2->fresh()->status)->toBe(OrderStatus::Processing)
        ->and($order2->fresh()->payment->payment_method)->toBe(\App\Enums\PaymentMethod::CashOnDelivery)
        ->and($this->variant->fresh()->stock_reserved)->toBe(0)
        ->and($this->variant->fresh()->stock_physical)->toBe(7);
});
