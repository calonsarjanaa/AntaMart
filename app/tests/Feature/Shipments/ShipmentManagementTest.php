<?php

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::create([
        'name' => 'Shipment Owner', 'email' => 'shipment-owner@example.com', 'password' => bcrypt('password'), 'role' => 'store_owner',
    ]);
    $this->customer = User::create([
        'name' => 'Shipment Customer', 'email' => 'shipment-customer@example.com', 'password' => bcrypt('password'), 'role' => 'customer',
    ]);
    $this->makeOrder = function (string $suffix, OrderStatus $status = OrderStatus::Processing): Order {
        return Order::create([
            'guest_email'     => "shipment-{$suffix}@example.com",
            'guest_phone'     => '081234567890',
            'status'          => $status,
            'subtotal'        => 70000,
            'shipping_cost'   => 0,
            'total'           => 70000,
            'idempotency_key' => "shipment-{$suffix}",
        ]);
    };
});

test('owner creates manual shipment and confirms delivery through valid order transitions', function () {
    $order = ($this->makeOrder)('lifecycle');

    $this->actingAs($this->owner)->postJson("/api/admin/orders/{$order->id}/shipment", [
        'tracking_number' => 'MANUAL-TRACK-001',
        'tracking_url'    => 'https://tracking.example.test/MANUAL-TRACK-001',
    ])->assertCreated()->assertJsonPath('data.method', 'manual');

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped)
        ->and(Shipment::where('order_id', $order->id)->first()->tracking_number)->toBe('MANUAL-TRACK-001')
        ->and(AuditLog::where('order_id', $order->id)->where('event', 'tracking.added')->count())->toBe(1);

    $this->actingAs($this->owner)->postJson("/api/admin/orders/{$order->id}/shipment/confirm-delivery")
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Completed)
        ->and(Shipment::where('order_id', $order->id)->first()->delivered_at)->not->toBeNull()
        ->and(AuditLog::where('order_id', $order->id)->where('event', 'shipment.delivered')->count())->toBe(1);
});

test('shipment actions reject invalid state transitions and duplicate delivery confirmation', function () {
    $processingOrder = ($this->makeOrder)('processing');
    $shippedOrder = ($this->makeOrder)('already-shipped', OrderStatus::Shipped);

    $this->actingAs($this->owner)->postJson("/api/admin/orders/{$shippedOrder->id}/shipment", [
        'tracking_number' => 'MANUAL-TRACK-002',
    ])->assertUnprocessable();

    $this->actingAs($this->owner)->postJson("/api/admin/orders/{$processingOrder->id}/shipment/confirm-delivery")
        ->assertUnprocessable();

    $this->actingAs($this->owner)->postJson("/api/admin/orders/{$shippedOrder->id}/shipment/confirm-delivery")
        ->assertUnprocessable();

    expect(Shipment::count())->toBe(0);
});

test('shipment endpoints require store owner role and tracking number', function () {
    $order = ($this->makeOrder)('authorization');
    $url = "/api/admin/orders/{$order->id}/shipment";

    $this->actingAs($this->customer)->postJson($url, ['tracking_number' => 'DENIED'])
        ->assertForbidden();

    $this->actingAs($this->owner)->postJson($url, [])->assertUnprocessable()
        ->assertJsonValidationErrors(['tracking_number']);

    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
});
