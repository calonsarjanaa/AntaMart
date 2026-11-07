<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->customer = User::create([
        'name' => 'Tracked Customer', 'email' => 'tracked-customer@example.com', 'password' => bcrypt('password'), 'role' => 'customer',
    ]);
    $this->guestOrder = Order::create([
        'guest_email'     => 'track-guest@example.com',
        'guest_phone'     => '081234567890',
        'status'          => OrderStatus::Shipped,
        'subtotal'        => 30000,
        'shipping_cost'   => 5000,
        'total'           => 35000,
        'idempotency_key' => 'tracking-guest-order',
    ]);
    $this->item = OrderItem::create([
        'order_id' => $this->guestOrder->id, 'product_id' => 111, 'product_variant_id' => 211,
        'product_snapshot' => ['name' => 'Tracked Product'], 'variant_snapshot' => ['name' => 'Blue'],
        'sku' => 'TRACK-001', 'quantity' => 1, 'unit_price' => 30000, 'subtotal' => 30000,
    ]);
    Payment::create([
        'order_id' => $this->guestOrder->id, 'amount' => 35000,
        'status' => PaymentStatus::Paid, 'paid_at' => now(),
    ]);
    Shipment::create([
        'order_id' => $this->guestOrder->id, 'method' => 'manual',
        'tracking_number' => 'TRACK-001', 'tracking_url' => 'https://tracking.example.test/TRACK-001',
        'shipped_at' => now()->subDay(),
    ]);
    Refund::create([
        'order_id' => $this->guestOrder->id, 'amount' => 35000,
        'reason' => 'Tracking test refund.', 'status' => RefundStatus::ManualRefundPending,
    ]);
});

test('guest can track order details and shipment by order ID and matching email', function () {
    $response = $this->getJson('/api/track?order_id='.$this->guestOrder->id.'&email=TRACK-GUEST%40example.com');

    $response->assertOk()
        ->assertJsonPath('data.order_id', $this->guestOrder->id)
        ->assertJsonPath('data.status', 'shipped')
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.items.0.product_name', 'Tracked Product')
        ->assertJsonPath('data.shipment.tracking_number', 'TRACK-001')
        ->assertJsonPath('data.refund_status', 'manual_refund_pending')
        ->assertJsonMissingPath('data.guest_email')
        ->assertJsonMissingPath('data.shipping_address');
});

test('tracking returns the same not-found response for wrong email and unknown order', function () {
    $wrongEmail = $this->getJson('/api/track?order_id='.$this->guestOrder->id.'&email=attacker%40example.com');
    $unknownOrder = $this->getJson('/api/track?order_id=01JAZZZZZZZZZZZZZZZZZZZZZZ&email=track-guest%40example.com');

    $wrongEmail->assertNotFound()->assertExactJson(['message' => 'Order not found.']);
    $unknownOrder->assertNotFound()->assertExactJson(['message' => 'Order not found.']);
});

test('guest tracking endpoint does not return authenticated customer orders', function () {
    $customerOrder = Order::create([
        'customer_id' => $this->customer->id,
        'status' => OrderStatus::Processing,
        'subtotal' => 10000,
        'shipping_cost' => 0,
        'total' => 10000,
        'idempotency_key' => 'tracking-auth-order',
    ]);

    $this->getJson('/api/track?order_id='.$customerOrder->id.'&email=tracked-customer%40example.com')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Order not found.']);
});

test('tracking route throttles after ten requests per minute from one IP', function () {
    $url = '/api/track?order_id='.$this->guestOrder->id.'&email=track-guest%40example.com';

    for ($i = 0; $i < 10; $i++) {
        $this->getJson($url)->assertOk();
    }

    $this->getJson($url)->assertStatus(429);
});
