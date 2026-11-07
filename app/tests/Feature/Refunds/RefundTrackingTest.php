<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\AuditLog;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::create([
        'name' => 'Refund Owner', 'email' => 'refund-owner-feature@example.com', 'password' => bcrypt('password'), 'role' => 'store_owner',
    ]);
    $this->customer = User::create([
        'name' => 'Refund Customer', 'email' => 'refund-customer-feature@example.com', 'password' => bcrypt('password'), 'role' => 'customer',
    ]);

    $productA = Product::create(['id' => 91, 'name' => 'Resellable Item A', 'slug' => 'resellable-item-a']);
    $productB = Product::create(['id' => 92, 'name' => 'Non-resellable Item B', 'slug' => 'non-resellable-item-b']);

    $this->variantA = ProductVariant::create([
        'product_id' => $productA->id, 'sku' => 'REFUND-A', 'name' => 'Resellable Item A',
        'price' => 30000, 'stock_physical' => 7, 'stock_reserved' => 0, 'is_active' => true,
    ]);
    $this->variantB = ProductVariant::create([
        'product_id' => $productB->id, 'sku' => 'REFUND-B', 'name' => 'Non-resellable Item B',
        'price' => 45000, 'stock_physical' => 4, 'stock_reserved' => 0, 'is_active' => true,
    ]);

    $this->order = Order::create([
        'guest_email'     => 'refund-guest@example.com',
        'guest_phone'     => '081234567891',
        'status'          => OrderStatus::Cancelled,
        'subtotal'        => 105000,
        'shipping_cost'   => 0,
        'total'           => 105000,
        'idempotency_key' => 'refund-tracking-main',
    ]);
    $this->payment = Payment::create([
        'order_id' => $this->order->id,
        'amount'   => 105000,
        'status'   => PaymentStatus::Paid,
        'paid_at'  => now(),
    ]);
    $this->refund = Refund::create([
        'order_id' => $this->order->id,
        'amount'   => 105000,
        'reason'   => 'Paid cancellation.',
        'status'   => RefundStatus::ManualRefundPending,
    ]);

    $this->itemA = OrderItem::create([
        'order_id' => $this->order->id, 'product_id' => 91, 'product_variant_id' => $this->variantA->id,
        'product_snapshot' => ['name' => 'Resellable Item A'], 'variant_snapshot' => ['name' => 'A'],
        'sku' => 'REFUND-A', 'quantity' => 2, 'unit_price' => 30000, 'subtotal' => 60000,
    ]);
    $this->itemB = OrderItem::create([
        'order_id' => $this->order->id, 'product_id' => 92, 'product_variant_id' => $this->variantB->id,
        'product_snapshot' => ['name' => 'Non-resellable Item B'], 'variant_snapshot' => ['name' => 'B'],
        'sku' => 'REFUND-B', 'quantity' => 1, 'unit_price' => 45000, 'subtotal' => 45000,
    ]);
    InventoryReservation::create([
        'order_id' => $this->order->id, 'product_variant_id' => $this->variantA->id,
        'quantity' => 2, 'status' => 'converted', 'reserved_at' => now()->subHour(),
    ]);
    InventoryReservation::create([
        'order_id' => $this->order->id, 'product_variant_id' => $this->variantB->id,
        'quantity' => 1, 'status' => 'converted', 'reserved_at' => now()->subHour(),
    ]);
});

test('owner marks a pending refund complete and restocks only selected resellable items', function () {
    $this->actingAs($this->owner)->postJson("/api/admin/orders/{$this->order->id}/refund/mark-refunded", [
        'notes'               => 'Manual transfer completed.',
        'resellable_item_ids' => [$this->itemA->id],
    ])->assertOk()->assertJsonPath('data.status', 'refunded');

    $refund = $this->refund->fresh();
    expect($refund->refunded_by)->toBe($this->owner->id)
        ->and($refund->refunded_at)->not->toBeNull()
        ->and($refund->notes)->toBe('Manual transfer completed.')
        ->and($refund->resellable_item_ids)->toBe([$this->itemA->id])
        ->and($this->variantA->fresh()->stock_physical)->toBe(9)
        ->and($this->variantB->fresh()->stock_physical)->toBe(4)
        ->and(AuditLog::where('order_id', $this->order->id)->where('event', 'refund.marked_refunded')->count())->toBe(1);
});

test('a refund can only be marked once and a failed retry does not restock twice', function () {
    $url = "/api/admin/orders/{$this->order->id}/refund/mark-refunded";
    $payload = ['resellable_item_ids' => [$this->itemA->id]];

    $this->actingAs($this->owner)->postJson($url, $payload)->assertOk();
    $this->actingAs($this->owner)->postJson($url, $payload)->assertUnprocessable();

    expect($this->variantA->fresh()->stock_physical)->toBe(9)
        ->and(AuditLog::where('order_id', $this->order->id)->where('event', 'refund.marked_refunded')->count())->toBe(1);
});

test('refund endpoint rejects item IDs from another order without changing refund or stock', function () {
    $otherOrder = Order::create([
        'status' => OrderStatus::Cancelled, 'subtotal' => 1, 'shipping_cost' => 0, 'total' => 1,
        'idempotency_key' => 'refund-tracking-other',
    ]);
    $foreignItem = OrderItem::create([
        'order_id' => $otherOrder->id, 'product_id' => 91, 'product_variant_id' => $this->variantA->id,
        'product_snapshot' => ['name' => 'Foreign'], 'sku' => 'FOREIGN',
        'quantity' => 1, 'unit_price' => 1, 'subtotal' => 1,
    ]);

    $this->actingAs($this->owner)->postJson("/api/admin/orders/{$this->order->id}/refund/mark-refunded", [
        'resellable_item_ids' => [$foreignItem->id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['resellable_item_ids']);

    expect($this->refund->fresh()->status)->toBe(RefundStatus::ManualRefundPending)
        ->and($this->variantA->fresh()->stock_physical)->toBe(7);
});

test('late-payment refund cannot restock inventory that was never deducted', function () {
    $this->order->update(['status' => OrderStatus::Expired]);
    InventoryReservation::where('order_id', $this->order->id)->update(['status' => 'released']);

    $this->actingAs($this->owner)->postJson("/api/admin/orders/{$this->order->id}/refund/mark-refunded", [
        'resellable_item_ids' => [$this->itemA->id],
    ])->assertUnprocessable();

    expect($this->refund->fresh()->status)->toBe(RefundStatus::ManualRefundPending)
        ->and($this->variantA->fresh()->stock_physical)->toBe(7);
});

test('customer cannot mark a manual refund as completed', function () {
    $this->actingAs($this->customer)->postJson("/api/admin/orders/{$this->order->id}/refund/mark-refunded", [])
        ->assertForbidden();

    expect($this->refund->fresh()->status)->toBe(RefundStatus::ManualRefundPending);
});
