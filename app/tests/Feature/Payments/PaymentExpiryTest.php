<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\ExpireUnpaidOrder;
use App\Models\AuditLog;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->variant = ProductVariant::create([
        'product_id'     => 71,
        'sku'            => 'EXPIRY-01',
        'name'           => 'Expiry Test Item',
        'price'          => 25000,
        'stock_physical' => 8,
        'stock_reserved' => 2,
        'is_active'      => true,
    ]);

    $this->order = Order::create([
        'guest_email'     => 'expiry@example.com',
        'guest_phone'     => '081234567890',
        'status'          => OrderStatus::PendingPayment,
        'subtotal'        => 50000,
        'shipping_cost'   => 0,
        'total'           => 50000,
        'idempotency_key' => 'expiry-test-order',
        'expires_at'      => now()->subSecond(),
    ]);

    $this->payment = Payment::create([
        'order_id' => $this->order->id,
        'amount'   => 50000,
        'status'   => PaymentStatus::Pending,
    ]);

    $this->reservation = InventoryReservation::create([
        'order_id'           => $this->order->id,
        'product_variant_id' => $this->variant->id,
        'quantity'           => 2,
        'status'             => 'reserved',
        'reserved_at'        => now()->subMinutes(15),
    ]);
});

test('expired unpaid order releases inventory and records payment expiry once', function () {
    ExpireUnpaidOrder::dispatchSync($this->order->id);
    ExpireUnpaidOrder::dispatchSync($this->order->id);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Expired)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($this->reservation->fresh()->status)->toBe('released')
        ->and($this->variant->fresh()->stock_physical)->toBe(8)
        ->and($this->variant->fresh()->stock_reserved)->toBe(0)
        ->and(AuditLog::where('order_id', $this->order->id)->where('event', 'payment.expired')->count())->toBe(1);
});

test('expiry job leaves an order alone before its invoice expires', function () {
    $this->order->update(['expires_at' => now()->addMinutes(5)]);

    ExpireUnpaidOrder::dispatchSync($this->order->id);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($this->reservation->fresh()->status)->toBe('reserved')
        ->and($this->variant->fresh()->stock_reserved)->toBe(2)
        ->and(AuditLog::where('event', 'payment.expired')->count())->toBe(0);
});

test('expiry job does not expire an order whose payment is already paid', function () {
    $this->payment->update([
        'status'  => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    ExpireUnpaidOrder::dispatchSync($this->order->id);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->reservation->fresh()->status)->toBe('reserved')
        ->and($this->variant->fresh()->stock_reserved)->toBe(2);
});
