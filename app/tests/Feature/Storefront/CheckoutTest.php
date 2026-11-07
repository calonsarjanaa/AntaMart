<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->variantA = ProductVariant::create([
        'product_id'     => 101,
        'sku'            => 'SHIRT-BLUE-M',
        'name'           => 'Blue Cotton Shirt (Medium)',
        'price'          => 150000.00,
        'stock_physical' => 20,
        'stock_reserved' => 0,
        'is_active'      => true,
    ]);

    $this->variantB = ProductVariant::create([
        'product_id'     => 102,
        'sku'            => 'PANTS-BLACK-32',
        'name'           => 'Black Chino Pants (Size 32)',
        'price'          => 250000.00,
        'stock_physical' => 5,
        'stock_reserved' => 0,
        'is_active'      => true,
    ]);
});

test('guest checkout successfully creates order, snapshots, stock reservation, and payment per AC-TRX-001', function () {
    $payload = [
        'idempotency_key'  => 'checkout-guest-unique-001',
        'guest_email'      => 'guest@example.com',
        'guest_phone'      => '081234567890',
        'items'            => [
            ['product_variant_id' => $this->variantA->id, 'quantity' => 2],
            ['product_variant_id' => $this->variantB->id, 'quantity' => 1],
        ],
        'shipping_address' => [
            'recipient_name' => 'Budi Santoso',
            'phone'          => '081234567890',
            'address_line1'  => 'Jl. Thamrin No. 45',
            'city'           => 'Jakarta Pusat',
            'province'       => 'DKI Jakarta',
            'postal_code'    => '10350',
        ],
    ];

    $response = $this->postJson('/api/checkout', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.subtotal', '550000.00')
        ->assertJsonPath('data.total', '550000.00');

    $orderId = $response->json('data.order_id');
    $order = Order::find($orderId);

    expect($order)->not->toBeNull()
        ->and($order->isGuest())->toBeTrue()
        ->and($order->guest_email)->toBe('guest@example.com')
        ->and($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->items)->toHaveCount(2)
        ->and($order->address->recipient_name)->toBe('Budi Santoso')
        ->and($order->payment->status)->toBe(PaymentStatus::Pending)
        ->and($order->payment->amount)->toBe('550000.00');

    // FR-INV-001: Stock reservations created
    expect($order->reservations)->toHaveCount(2);

    // FR-INV-002: Available stock decreases because stock_reserved increases
    expect($this->variantA->fresh()->stock_reserved)->toBe(2)
        ->and($this->variantA->fresh()->available_stock)->toBe(18)
        ->and($this->variantB->fresh()->stock_reserved)->toBe(1)
        ->and($this->variantB->fresh()->available_stock)->toBe(4);
});

test('authenticated customer checkout associates order with user', function () {
    $customer = User::create([
        'name'     => 'Alice Smith',
        'email'    => 'alice@example.com',
        'password' => bcrypt('password'),
        'role'     => 'customer',
    ]);

    $payload = [
        'idempotency_key'  => 'checkout-auth-user-001',
        'items'            => [
            ['product_variant_id' => $this->variantA->id, 'quantity' => 1],
        ],
        'shipping_address' => [
            'recipient_name' => 'Alice Smith',
            'phone'          => '08111222333',
            'address_line1'  => 'Jl. Senopati No. 10',
            'city'           => 'Jakarta Selatan',
            'province'       => 'DKI Jakarta',
            'postal_code'    => '12190',
        ],
    ];

    $response = $this->actingAs($customer)->postJson('/api/checkout', $payload);

    $response->assertStatus(201);

    $order = Order::find($response->json('data.order_id'));
    expect($order->customer_id)->toBe($customer->id)
        ->and($order->isGuest())->toBeFalse();
});

test('checkout fails when requested quantity exceeds available stock per FR-INV-003', function () {
    $payload = [
        'idempotency_key'  => 'checkout-fail-stock-001',
        'guest_email'      => 'shopper@example.com',
        'guest_phone'      => '081299998888',
        'items'            => [
            ['product_variant_id' => $this->variantB->id, 'quantity' => 10], // Only 5 available!
        ],
        'shipping_address' => [
            'recipient_name' => 'Shopper',
            'phone'          => '081299998888',
            'address_line1'  => 'Jl. Melati',
            'city'           => 'Bandung',
            'province'       => 'Jawa Barat',
            'postal_code'    => '40115',
        ],
    ];

    $response = $this->postJson('/api/checkout', $payload);

    $response->assertStatus(422);

    // No order or reservation should exist
    expect(Order::count())->toBe(0)
        ->and($this->variantB->fresh()->stock_reserved)->toBe(0)
        ->and($this->variantB->fresh()->available_stock)->toBe(5);
});

test('idempotency key prevents duplicate checkout and does not reserve double stock per FR-TRX-005', function () {
    $payload = [
        'idempotency_key'  => 'same-idempotency-key-12345',
        'guest_email'      => 'buyer@example.com',
        'guest_phone'      => '081211112222',
        'items'            => [
            ['product_variant_id' => $this->variantA->id, 'quantity' => 3],
        ],
        'shipping_address' => [
            'recipient_name' => 'Buyer',
            'phone'          => '081211112222',
            'address_line1'  => 'Jl. Sudirman',
            'city'           => 'Surabaya',
            'province'       => 'Jawa Timur',
            'postal_code'    => '60271',
        ],
    ];

    // First request
    $response1 = $this->postJson('/api/checkout', $payload);
    $response1->assertStatus(201);
    $firstOrderId = $response1->json('data.order_id');

    // Duplicate request (e.g. double click or network retry)
    $response2 = $this->postJson('/api/checkout', $payload);
    $response2->assertStatus(201);
    $secondOrderId = $response2->json('data.order_id');

    // Both responses return the EXACT same order
    expect($secondOrderId)->toBe($firstOrderId)
        ->and(Order::count())->toBe(1);

    // Stock was reserved exactly once (3 units, not 6)
    expect($this->variantA->fresh()->stock_reserved)->toBe(3)
        ->and($this->variantA->fresh()->available_stock)->toBe(17);
});

test('guest checkout validation fails when guest email is missing', function () {
    $payload = [
        'idempotency_key'  => 'guest-no-email-001',
        'guest_phone'      => '081211112222',
        'items'            => [
            ['product_variant_id' => $this->variantA->id, 'quantity' => 1],
        ],
        'shipping_address' => [
            'recipient_name' => 'No Email',
            'phone'          => '081211112222',
            'address_line1'  => 'Jl. Gajah Mada',
            'city'           => 'Medan',
            'province'       => 'Sumatera Utara',
            'postal_code'    => '20112',
        ],
    ];

    $response = $this->postJson('/api/checkout', $payload);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['guest_email']);
});


test('concurrent checkout requests for last unit only allow one to succeed per EC-01', function () {
    // Only 1 item physically available
    $limitedVariant = ProductVariant::create([
        'product_id'     => 103,
        'sku'            => 'LIMITED-EDITION-01',
        'name'           => 'Rare Collector Item',
        'price'          => 1000000.00,
        'stock_physical' => 1,
        'stock_reserved' => 0,
        'is_active'      => true,
    ]);

    $order1Payload = [
        'idempotency_key'  => 'order-buyer-1',
        'guest_email'      => 'buyer1@example.com',
        'guest_phone'      => '0811111111',
        'items'            => [
            ['product_variant_id' => $limitedVariant->id, 'quantity' => 1],
        ],
        'shipping_address' => [
            'recipient_name' => 'Buyer 1',
            'phone'          => '0811111111',
            'address_line1'  => 'Jl. Raya',
            'city'           => 'Jakarta',
            'province'       => 'DKI Jakarta',
            'postal_code'    => '10110',
        ],
    ];

    $order2Payload = [
        'idempotency_key'  => 'order-buyer-2',
        'guest_email'      => 'buyer2@example.com',
        'guest_phone'      => '0822222222',
        'items'            => [
            ['product_variant_id' => $limitedVariant->id, 'quantity' => 1],
        ],
        'shipping_address' => [
            'recipient_name' => 'Buyer 2',
            'phone'          => '0822222222',
            'address_line1'  => 'Jl. Raya',
            'city'           => 'Jakarta',
            'province'       => 'DKI Jakarta',
            'postal_code'    => '10110',
        ],
    ];

    // First buyer checks out successfully
    $response1 = $this->postJson('/api/checkout', $order1Payload);
    $response1->assertStatus(201);

    // Second buyer attempts to check out for the same variant — must be rejected
    $response2 = $this->postJson('/api/checkout', $order2Payload);
    $response2->assertStatus(422);

    expect($limitedVariant->fresh()->stock_reserved)->toBe(1)
        ->and($limitedVariant->fresh()->available_stock)->toBe(0)
        ->and(Order::count())->toBe(1);
});
