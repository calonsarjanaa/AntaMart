<?php

use App\Enums\CancellationStatus;
use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Models\AuditLog;
use App\Models\CancellationRequest;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cancellation request links to requester and decider', function () {
    $customer = User::create([
        'name' => 'Cust', 'email' => 'c@example.com', 'password' => 'secret', 'role' => 'customer',
    ]);
    $owner = User::create([
        'name' => 'Owner', 'email' => 'o@example.com', 'password' => 'secret', 'role' => 'store_owner',
    ]);
    $order = Order::create([
        'customer_id' => $customer->id, 'status' => OrderStatus::Processing,
        'subtotal' => 1000, 'shipping_cost' => 0, 'total' => 1000, 'idempotency_key' => 'id-op-1',
    ]);

    $cancelReq = CancellationRequest::create([
        'order_id'         => $order->id,
        'requested_by'     => $customer->id,
        'reason'           => 'Changed mind',
        'status'           => CancellationStatus::Pending,
        'rejection_reason' => null,
    ]);

    expect($cancelReq->isPending())->toBeTrue()
        ->and($cancelReq->isDecided())->toBeFalse()
        ->and($cancelReq->order->id)->toBe($order->id)
        ->and($cancelReq->requester->id)->toBe($customer->id);

    // Decision update
    $cancelReq->update([
        'status'           => CancellationStatus::Rejected,
        'rejection_reason' => 'Item is already packed',
        'decided_by'       => $owner->id,
        'decided_at'       => now(),
    ]);

    expect($cancelReq->fresh()->isDecided())->toBeTrue()
        ->and($cancelReq->fresh()->decider->id)->toBe($owner->id)
        ->and($cancelReq->fresh()->rejection_reason)->toBe('Item is already packed');
});

test('refund model tracks manual refund fields correctly', function () {
    $owner = User::create([
        'name' => 'Owner', 'email' => 'refund-owner@example.com', 'password' => 'secret', 'role' => 'store_owner',
    ]);
    $order = Order::create([
        'status' => OrderStatus::Cancelled, 'subtotal' => 50000, 'shipping_cost' => 10000,
        'total' => 60000, 'idempotency_key' => 'id-ref-1',
    ]);

    $refund = Refund::create([
        'order_id' => $order->id,
        'amount'   => 60000,
        'reason'   => 'Order cancelled by owner',
        'status'   => RefundStatus::ManualRefundPending,
    ]);

    expect($refund->isPending())->toBeTrue()
        ->and($refund->isRefunded())->toBeFalse();

    $refund->update([
        'status'      => RefundStatus::Refunded,
        'refunded_by' => $owner->id,
        'refunded_at' => now(),
        'notes'       => 'Bank transfer BCA completed. Ref: #TRX-998',
    ]);

    expect($refund->fresh()->isRefunded())->toBeTrue()
        ->and($refund->fresh()->refundedBy->id)->toBe($owner->id);
});

test('shipment tracks delivery status', function () {
    $order = Order::create([
        'status' => OrderStatus::Processing, 'subtotal' => 10000, 'shipping_cost' => 0,
        'total' => 10000, 'idempotency_key' => 'id-ship-1',
    ]);

    $shipment = Shipment::create([
        'order_id'        => $order->id,
        'method'          => 'manual',
        'tracking_number' => 'MANUAL-123',
        'shipped_at'      => now(),
    ]);

    expect($shipment->isShipped())->toBeTrue()
        ->and($shipment->isDelivered())->toBeFalse();

    $shipment->update(['delivered_at' => now()]);
    expect($shipment->fresh()->isDelivered())->toBeTrue();
});

test('audit log appends correctly without updated_at', function () {
    $order = Order::create([
        'status' => OrderStatus::PendingPayment, 'subtotal' => 10000, 'shipping_cost' => 0,
        'total' => 10000, 'idempotency_key' => 'id-audit-1',
    ]);

    $log = AuditLog::create([
        'order_id'   => $order->id,
        'actor_type' => 'customer',
        'event'      => 'order.created',
        'payload'    => ['total' => 10000],
    ]);

    expect($log->event)->toBe('order.created')
        ->and($log->order->id)->toBe($order->id)
        ->and($log->payload)->toBe(['total' => 10000]);
});
