<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    public function createManualShipment(Order $order, string $trackingNumber, ?string $trackingUrl = null): Shipment
    {
        return DB::transaction(function () use ($order, $trackingNumber, $trackingUrl): Shipment {
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

            return $order->shipment()->create([
                'method'          => 'manual',
                'tracking_number' => $trackingNumber,
                'tracking_url'    => $trackingUrl,
                'shipped_at'      => now(),
            ]);
        });
    }

    public function confirmDelivery(Order $order): Shipment
    {
        return DB::transaction(function () use ($order): Shipment {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::Shipped) {
                throw ValidationException::withMessages([
                    'order' => 'Only shipped orders can be marked as delivered.',
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
            $order->transitionTo(OrderStatus::Completed);

            return $shipment->fresh();
        });
    }
}
