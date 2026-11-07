<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RefundService
{
    public function flagForOrder(Order $order, string $reason): ?Refund
    {
        $payment = $order->payment()->first();

        if (!$payment || $payment->status !== PaymentStatus::Paid) {
            return null;
        }

        $refund = Refund::firstOrNew(['order_id' => $order->id]);

        if (!$refund->exists || $refund->status === RefundStatus::NotRequested) {
            $refund->amount = $payment->amount;
            $refund->reason = $reason;
            $refund->status = RefundStatus::ManualRefundPending;
            $refund->save();
        }

        return $refund;
    }

    /**
     * @param array<int, int> $resellableItemIds
     */
    public function markRefunded(
        Order $order,
        User $owner,
        ?string $notes,
        array $resellableItemIds
    ): Refund {
        return DB::transaction(function () use ($order, $owner, $notes, $resellableItemIds): Refund {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $refund = Refund::where('order_id', $order->id)->lockForUpdate()->firstOrFail();

            if ($refund->status !== RefundStatus::ManualRefundPending) {
                throw ValidationException::withMessages([
                    'refund' => 'Only refunds marked as manual_refund_pending can be confirmed.',
                ]);
            }

            $itemIds = array_values(array_unique(array_map('intval', $resellableItemIds)));
            sort($itemIds);

            $orderItems = $order->items()
                ->whereIn('id', $itemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($orderItems->count() !== count($itemIds)) {
                throw ValidationException::withMessages([
                    'resellable_item_ids' => 'Every resellable item ID must belong to this order.',
                ]);
            }

            $restockQuantities = [];
            foreach ($orderItems as $item) {
                $variantId = (int) $item->product_variant_id;
                $restockQuantities[$variantId] = ($restockQuantities[$variantId] ?? 0) + $item->quantity;
            }

            ksort($restockQuantities);

            $convertedQuantities = $order->reservations()
                ->where('status', 'converted')
                ->get(['product_variant_id', 'quantity'])
                ->groupBy('product_variant_id')
                ->map(fn ($reservations) => $reservations->sum('quantity'));

            foreach ($restockQuantities as $variantId => $quantity) {
                if ($quantity > (int) ($convertedQuantities->get($variantId) ?? 0)) {
                    throw ValidationException::withMessages([
                        'resellable_item_ids' => 'Only stock previously deducted for this order can be restocked.',
                    ]);
                }

                $updated = DB::table('product_variants')
                    ->where('id', $variantId)
                    ->increment('stock_physical', $quantity);

                if ($updated !== 1) {
                    throw new RuntimeException("Cannot restock missing product variant [{$variantId}].");
                }
            }

            $refund->update([
                'status'               => RefundStatus::Refunded,
                'refunded_at'          => now(),
                'refunded_by'          => $owner->id,
                'notes'                => $notes,
                'resellable_item_ids'  => $itemIds,
            ]);

            return $refund->fresh();
        });
    }
}
