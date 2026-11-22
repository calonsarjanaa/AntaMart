<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Jobs\ExpireUnpaidOrder;
use App\Models\Order;

class OrderExpiryService
{
    /**
     * Expire a single order immediately if it is still pending_payment past its expires_at deadline.
     */
    public function expireOrder(Order $order): bool
    {
        if (
            $order->status !== OrderStatus::PendingPayment
            || !$order->expires_at
            || $order->expires_at->isFuture()
        ) {
            return false;
        }

        ExpireUnpaidOrder::dispatchSync($order->id);

        return $order->fresh()?->status === OrderStatus::Expired;
    }

    /**
     * Sweep and expire overdue pending_payment orders in batches.
     *
     * @return int Number of orders transitioned to expired.
     */
    public function expireAllPastDue(int $limit = 100): int
    {
        $orderIds = Order::query()
            ->where('status', OrderStatus::PendingPayment->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit($limit)
            ->pluck('id');

        $expiredCount = 0;

        foreach ($orderIds as $orderId) {
            ExpireUnpaidOrder::dispatchSync((string) $orderId);

            if (Order::whereKey($orderId)->where('status', OrderStatus::Expired->value)->exists()) {
                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    /**
     * Release expired reservations holding stock for specific product variants
     * so new checkouts are never blocked by abandoned orders if the queue worker is down.
     *
     * @param array<int, int|string> $variantIds
     * @return int Number of orders transitioned to expired.
     */
    public function expirePastDueForVariants(array $variantIds): int
    {
        if ($variantIds === []) {
            return 0;
        }

        $orderIds = Order::query()
            ->where('status', OrderStatus::PendingPayment->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereHas('reservations', function ($query) use ($variantIds): void {
                $query->where('status', 'reserved')
                    ->whereIn('product_variant_id', $variantIds);
            })
            ->pluck('id');

        $expiredCount = 0;

        foreach ($orderIds as $orderId) {
            ExpireUnpaidOrder::dispatchSync((string) $orderId);

            if (Order::whereKey($orderId)->where('status', OrderStatus::Expired->value)->exists()) {
                $expiredCount++;
            }
        }

        return $expiredCount;
    }
}

