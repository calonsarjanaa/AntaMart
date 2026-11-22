<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\User;
use App\Services\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->service = app(WhatsAppNotificationService::class);

    $this->owner = User::factory()->create([
        'name' => 'Store Owner',
        'email' => 'owner-wa@example.test',
        'role' => 'store_owner',
    ]);

    $product = Product::create(['name' => 'Beras Premium 5kg', 'slug' => 'beras-premium-5kg', 'is_active' => true]);
    $this->variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'BERAS-5KG',
        'name' => '5kg',
        'price' => 75000,
        'stock_physical' => 10,
        'stock_reserved' => 0,
        'is_active' => true,
    ]);
});

test('normalizes indonesian phone numbers for wa.me links', function () {
    expect($this->service->normalizePhone('0812-3456-7890'))->toBe('6281234567890')
        ->and($this->service->normalizePhone('+62 812 3456 7890'))->toBe('6281234567890')
        ->and($this->service->normalizePhone('6281234567890'))->toBe('6281234567890')
        ->and($this->service->normalizePhone('81234567890'))->toBe('6281234567890')
        ->and($this->service->normalizePhone(''))->toBeNull();
});

test('builds QRIS paid whatsapp notification and shows button on owner order detail', function () {
    $order = Order::create([
        'guest_email' => 'budi@example.test',
        'guest_phone' => '081111111111',
        'status' => OrderStatus::Processing,
        'subtotal' => 75000,
        'shipping_cost' => 0,
        'total' => 75000,
        'idempotency_key' => 'wa-test-qris-paid',
    ]);

    OrderAddress::create([
        'order_id' => $order->id,
        'recipient_name' => 'Budi Santoso',
        'phone' => '0812-3456-7890',
        'address_line1' => 'Jl. Sudirman No. 1',
        'city' => 'Jakarta',
        'province' => 'DKI Jakarta',
        'postal_code' => '10220',
        'country' => 'ID',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $this->variant->product_id,
        'product_variant_id' => $this->variant->id,
        'product_snapshot' => ['name' => 'Beras Premium 5kg'],
        'variant_snapshot' => ['name' => '5kg'],
        'sku' => 'BERAS-5KG',
        'quantity' => 1,
        'unit_price' => 75000,
        'subtotal' => 75000,
    ]);

    Payment::create([
        'order_id' => $order->id,
        'payment_method' => PaymentMethod::Xendit,
        'amount' => 75000,
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $notification = $this->service->buildNotification($order);

    expect($notification)->not->toBeNull()
        ->and($notification['phone'])->toBe('6281234567890')
        ->and($notification['label'])->toBe('Kirim Konfirmasi Lunas (WhatsApp)')
        ->and($notification['message'])->toContain('Halo Budi Santoso,')
        ->and($notification['message'])->toContain('Metode Bayar: QRIS (Lunas)')
        ->and($notification['message'])->not->toContain('Xendit')
        ->and($notification['message'])->toContain('Beras Premium 5kg (5kg) x1')
        ->and($notification['url'])->toStartWith('https://wa.me/6281234567890?text=');

    $this->actingAs($this->owner)
        ->get(route('owner.orders.show', $order->id))
        ->assertOk()
        ->assertSee('Kirim Konfirmasi Lunas (WhatsApp)')
        ->assertSee('https://wa.me/6281234567890?text=', false);
});

test('builds dynamic templates for shipped, COD processing, refund, and hides button for pending_payment', function () {
    // 1. Pending payment -> hidden
    $pendingOrder = Order::create([
        'guest_email' => 'pending@example.test',
        'guest_phone' => '081234567890',
        'status' => OrderStatus::PendingPayment,
        'subtotal' => 75000,
        'shipping_cost' => 0,
        'total' => 75000,
        'idempotency_key' => 'wa-test-pending',
        'expires_at' => now()->addMinutes(10),
    ]);
    expect($this->service->buildNotification($pendingOrder))->toBeNull();

    // 2. Shipped order includes tracking number
    $shippedOrder = Order::create([
        'guest_email' => 'shipped@example.test',
        'guest_phone' => '081234567890',
        'status' => OrderStatus::Shipped,
        'subtotal' => 75000,
        'shipping_cost' => 0,
        'total' => 75000,
        'idempotency_key' => 'wa-test-shipped',
    ]);
    Payment::create([
        'order_id' => $shippedOrder->id,
        'payment_method' => PaymentMethod::Xendit,
        'amount' => 75000,
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);
    Shipment::create([
        'order_id' => $shippedOrder->id,
        'method' => 'manual',
        'tracking_number' => 'JNE-99887766',
        'tracking_url' => 'https://cekresi.test/JNE-99887766',
        'shipped_at' => now(),
    ]);

    $shippedNotif = $this->service->buildNotification($shippedOrder);
    expect($shippedNotif['label'])->toBe('Kirim Info Pengiriman (WhatsApp)')
        ->and($shippedNotif['message'])->toContain('No. Resi: JNE-99887766')
        ->and($shippedNotif['message'])->toContain('Metode Bayar: QRIS (Lunas)');

    // 3. Cancelled with refunded status
    $refundedOrder = Order::create([
        'guest_email' => 'refund@example.test',
        'guest_phone' => '081234567890',
        'status' => OrderStatus::Cancelled,
        'subtotal' => 75000,
        'shipping_cost' => 0,
        'total' => 75000,
        'idempotency_key' => 'wa-test-refunded',
    ]);
    Payment::create([
        'order_id' => $refundedOrder->id,
        'payment_method' => PaymentMethod::Xendit,
        'amount' => 75000,
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);
    Refund::create([
        'order_id' => $refundedOrder->id,
        'amount' => 75000,
        'reason' => 'Stok habis',
        'status' => RefundStatus::Refunded,
        'refunded_at' => now(),
    ]);

    $refundNotif = $this->service->buildNotification($refundedOrder);
    expect($refundNotif['label'])->toBe('Kirim Info Refund Selesai (WhatsApp)')
        ->and($refundNotif['message'])->toContain('Status Refund: Selesai (Rp 75.000)');
});

