<?php

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStateException;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('order generates ULID primary key automatically', function () {
    $order = Order::create([
        'customer_id'     => null,
        'guest_email'     => 'guest@example.com',
        'guest_phone'     => '08123456789',
        'status'          => OrderStatus::PendingPayment,
        'subtotal'        => 100000.00,
        'shipping_cost'   => 15000.00,
        'total'           => 115000.00,
        'idempotency_key' => 'idemp-test-1',
    ]);

    expect($order->id)->toBeString()
        ->and(strlen($order->id))->toBe(26)
        ->and($order->isGuest())->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::PendingPayment);
});

test('order transitionTo succeeds for valid state changes', function () {
    $order = Order::create([
        'guest_email'     => 'guest@example.com',
        'status'          => OrderStatus::PendingPayment,
        'subtotal'        => 50000.00,
        'shipping_cost'   => 10000.00,
        'total'           => 60000.00,
        'idempotency_key' => 'idemp-test-2',
    ]);

    $order->transitionTo(OrderStatus::Processing);
    expect($order->fresh()->status)->toBe(OrderStatus::Processing);

    $order->transitionTo(OrderStatus::Shipped);
    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);

    $order->transitionTo(OrderStatus::Completed);
    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
});

test('order transitionTo throws InvalidOrderStateException for illegal state changes', function () {
    $order = Order::create([
        'guest_email'     => 'guest@example.com',
        'status'          => OrderStatus::PendingPayment,
        'subtotal'        => 50000.00,
        'shipping_cost'   => 10000.00,
        'total'           => 60000.00,
        'idempotency_key' => 'idemp-test-3',
    ]);

    // Cannot jump from pending_payment directly to shipped or completed
    expect(fn () => $order->transitionTo(OrderStatus::Shipped))
        ->toThrow(InvalidOrderStateException::class);
});

test('order correctly relates to items, address, and payment', function () {
    $user = User::create([
        'name'     => 'Test Customer',
        'email'    => 'cust@example.com',
        'password' => bcrypt('password'),
        'role'     => 'customer',
    ]);

    $order = Order::create([
        'customer_id'     => $user->id,
        'status'          => OrderStatus::PendingPayment,
        'subtotal'        => 20000.00,
        'shipping_cost'   => 5000.00,
        'total'           => 25000.00,
        'idempotency_key' => 'idemp-test-4',
    ]);

    $item = OrderItem::create([
        'order_id'           => $order->id,
        'product_id'         => 1,
        'product_variant_id' => 1,
        'product_snapshot'   => ['name' => 'Sample Product'],
        'sku'                => 'SKU-001',
        'quantity'           => 1,
        'unit_price'         => 20000.00,
        'subtotal'           => 20000.00,
    ]);

    $address = OrderAddress::create([
        'order_id'       => $order->id,
        'recipient_name' => 'Test Recipient',
        'phone'          => '08123456789',
        'address_line1'  => 'Jl. Test No. 1',
        'city'           => 'Jakarta',
        'province'       => 'DKI Jakarta',
        'postal_code'    => '10110',
    ]);

    $payment = Payment::create([
        'order_id' => $order->id,
        'amount'   => 25000.00,
    ]);

    expect($order->customer->id)->toBe($user->id)
        ->and($order->isGuest())->toBeFalse()
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->id)->toBe($item->id)
        ->and($order->address->id)->toBe($address->id)
        ->and($order->payment->id)->toBe($payment->id);
});

