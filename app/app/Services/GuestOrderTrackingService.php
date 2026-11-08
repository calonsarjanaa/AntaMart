<?php

namespace App\Services;

use App\Models\Order;

class GuestOrderTrackingService
{
    public function find(string $orderId, string $email): ?Order
    {
        $order = Order::query()
            ->whereKey($orderId)
            ->whereNull('customer_id')
            ->with(['items', 'payment', 'shipment', 'refund'])
            ->first();

        if (!$order || strtolower(trim((string) $order->guest_email)) !== strtolower(trim($email))) {
            return null;
        }

        return $order;
    }
}
