<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\AuditEvent;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    public function createManualShipment(Order $order, User $owner, string $trackingNumber, ?string $trackingUrl = null): Shipment
    {
        return DB::transaction(function () use ($order, $owner, $trackingNumber, $trackingUrl): Shipment {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::Processing) {
                throw ValidationException::withMessages([
                    'order' => 'Only processing orders can be shipped.',
                ]);
            }

            if ($order->shipment()->exists()) {
                throw ValidationException::withMessages([
                    'shipment' => 'A shipment already exists for this order.',
                ]);
            }

            $order->transitionTo(OrderStatus::Shipped);

            $shipment = $order->shipment()->create([
                'method'          => 'manual',
                'tracking_number' => $trackingNumber,
                'tracking_url'    => $trackingUrl,
                'shipped_at'      => now(),
            ]);

            AuditEvent::dispatch(
                'tracking.added',
                $order->id,
                $owner->id,
                $owner->role,
                [
                    'shipment_id' => $shipment->id,
                    'method'      => $shipment->method,
                ]
            );

            return $shipment;
        });
    }

    public function confirmDelivery(Order $order, User $owner, bool $cashReceived = false): Shipment
    {
        return DB::transaction(function () use ($order, $owner, $cashReceived): Shipment {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::Shipped) {
                throw ValidationException::withMessages([
                    'order' => 'Only shipped orders can be marked as delivered.',
                ]);
            }

            $payment = $order->payment()->lockForUpdate()->first();
            $isCashOnDelivery = $payment?->payment_method === PaymentMethod::CashOnDelivery;

            if ($isCashOnDelivery && !$cashReceived) {
                throw ValidationException::withMessages([
                    'cash_received' => 'Konfirmasikan penerimaan uang tunai untuk menyelesaikan pesanan COD.',
                ]);
            }

            $shipment = $order->shipment()->lockForUpdate()->first();

            if (!$shipment || !$shipment->shipped_at || $shipment->delivered_at) {
                throw ValidationException::withMessages([
                    'shipment' => 'This order does not have an undelivered shipment.',
                ]);
            }

            $shipment->delivered_at = now();
            $shipment->save();

            if ($isCashOnDelivery) {
                $payment->status = PaymentStatus::Paid;
                $payment->paid_at = now();
                $payment->save();

                AuditEvent::dispatch(
                    'payment.cod_collected',
                    $order->id,
                    $owner->id,
                    $owner->role,
                    ['amount' => $payment->amount]
                );
            }

            $order->transitionTo(OrderStatus::Completed);

            AuditEvent::dispatch(
                'shipment.delivered',
                $order->id,
                $owner->id,
                $owner->role,
                ['shipment_id' => $shipment->id]
            );

            return $shipment->fresh();
        });
    }
}
