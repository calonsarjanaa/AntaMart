<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config()->set('services.xendit.secret_key', 'xnd_development_dummy_key');
    config()->set('services.xendit.invoice_duration', 3600);
    config()->set('services.store.whatsapp_number', '081299990000');

    $this->owner = User::create([
        'name' => 'Confirm Owner',
        'email' => 'confirm-owner@example.test',
        'password' => bcrypt('password'),
        'role' => 'store_owner',
    ]);

    $product = Product::create(['name' => 'Minyak Goreng 2L', 'slug' => 'minyak-goreng-2l', 'is_active' => true]);
    $this->variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'MINYAK-2L',
        'name' => '2 Liter',
        'price' => 38000,
        'stock_physical' => 5,
        'stock_reserved' => 0,
        'is_active' => true,
    ]);
});

test('owner rejects awaiting confirmation order with reason visible to customer and releases stock', function () {
    $this->post('/cart/items', ['product_variant_id' => $this->variant->id, 'quantity' => 2]);

    $checkoutResponse = $this->post('/checkout', [
        'guest_email' => 'reject-buyer@example.test',
        'guest_phone' => '081234567890',
        'payment_method' => 'xendit',
        'shipping_address' => [
            'recipient_name' => 'Ani',
            'phone' => '081234567890',
            'address_line1' => 'Jl. Melati No. 3',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40111',
        ],
    ])->assertRedirect();

    $order = Order::where('guest_email', 'reject-buyer@example.test')->firstOrFail();
    $resultUrl = $checkoutResponse->headers->get('Location');

    // Customer sees awaiting confirmation + WhatsApp inquiry button to store
    $this->get($resultUrl)
        ->assertOk()
        ->assertSee('Menunggu konfirmasi toko')
        ->assertSee('Tanya Konfirmasi via WhatsApp')
        ->assertSee('https://wa.me/6281299990000?text=', false);

    expect($this->variant->fresh()->stock_reserved)->toBe(2);

    // Owner sees confirmation panel and rejects with reason
    $this->actingAs($this->owner)
        ->get(route('owner.orders.show', $order->id))
        ->assertOk()
        ->assertSee('Konfirmasi Pesanan')
        ->assertSee('Terima Pesanan')
        ->assertSee('Tolak Pesanan');

    $this->actingAs($this->owner)
        ->post(route('owner.orders.reject', $order->id), [
            'rejection_reason' => 'Stok fisik di toko baru saja habis terjual offline.',
        ])
        ->assertRedirect(route('owner.orders.show', $order->id));

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->rejection_reason)->toBe('Stok fisik di toko baru saja habis terjual offline.')
        ->and($this->variant->fresh()->stock_reserved)->toBe(0)
        ->and($this->variant->fresh()->stock_physical)->toBe(5)
        ->and(Refund::where('order_id', $order->id)->exists())->toBeFalse()
        ->and(AuditLog::where('order_id', $order->id)->where('event', 'order.rejected')->exists())->toBeTrue();

    // Owner detail now shows WhatsApp button with rejection info
    $this->actingAs($this->owner)
        ->get(route('owner.orders.show', $order->id))
        ->assertOk()
        ->assertSee('Kirim Info Penolakan (WhatsApp)');

    // Customer result page displays rejection reason clearly
    auth()->logout();
    $this->get($resultUrl)
        ->assertOk()
        ->assertSee('Pesanan ditolak oleh toko')
        ->assertSee('Stok fisik di toko baru saja habis terjual offline.');
});

test('owner accepts QRIS order creating 60-minute invoice and WhatsApp payment link button', function () {
    Http::fake([
        'https://api.xendit.co/v2/invoices' => Http::response([
            'id' => 'inv_confirm_60m',
            'invoice_url' => 'https://checkout.xendit.co/web/inv_confirm_60m',
            'expiry_date' => now()->addMinutes(60)->toIso8601String(),
        ], 200),
    ]);

    $this->post('/cart/items', ['product_variant_id' => $this->variant->id, 'quantity' => 1]);
    $checkoutResponse = $this->post('/checkout', [
        'guest_email' => 'accept-qris@example.test',
        'guest_phone' => '081234567890',
        'payment_method' => 'xendit',
        'shipping_address' => [
            'recipient_name' => 'Rudi',
            'phone' => '081234567890',
            'address_line1' => 'Jl. Kenanga 5',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'postal_code' => '60111',
        ],
    ])->assertRedirect();

    $order = Order::where('guest_email', 'accept-qris@example.test')->firstOrFail();

    $this->actingAs($this->owner)
        ->post(route('owner.orders.accept', $order->id))
        ->assertRedirect(route('owner.orders.show', $order->id));

    Http::assertSent(fn ($request) => $request['invoice_duration'] === 3600 && $request['payment_methods'] === ['QRIS']);

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->fresh()->confirmed_at)->not->toBeNull()
        ->and($order->fresh()->payment->xendit_payment_url)->toBe('https://checkout.xendit.co/web/inv_confirm_60m');

    // Owner detail shows WhatsApp button to send QRIS payment link
    $this->actingAs($this->owner)
        ->get(route('owner.orders.show', $order->id))
        ->assertOk()
        ->assertSee('Kirim Link Bayar QRIS (WhatsApp)');
});

