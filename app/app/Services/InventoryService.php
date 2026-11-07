<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Events\AuditEvent;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    /**
     * Atomically reserve stock for an order's items per FR-INV-001 & FR-INV-003.
     * Uses DB-level atomic compare-and-increment to prevent overselling race conditions (EC-01).
     *
     * @param Order $order
     * @param array<int, array{product_variant_id: int, quantity: int}> $items
     * @throws InsufficientStockException
     */
    public function reserveStock(Order $order, array $items): void
    {
        foreach ($items as $item) {
            $variantId = $item['product_variant_id'];
            $quantity = (int) $item['quantity'];

            // Atomic update: only increments reserved stock if physical - reserved >= requested
            $updated = DB::table('product_variants')
                ->where('id', $variantId)
                ->where('is_active', true)
                ->whereRaw('(stock_physical - stock_reserved) >= ?', [$quantity])
                ->increment('stock_reserved', $quantity);

            if ($updated === 0) {
                $variant = ProductVariant::find($variantId);
                $available = $variant ? $variant->available_stock : 0;

                throw new InsufficientStockException(
                    productVariantId: $variantId,
                    requestedQuantity: $quantity,
                    availableStock: $available,
                    message: "Insufficient stock for product variant [ID: {$variantId}]. Requested: {$quantity}, Available: {$available}."
                );
            }

            InventoryReservation::create([
                'order_id'           => $order->id,
                'product_variant_id' => $variantId,
                'quantity'           => $quantity,
                'status'             => 'reserved',
                'reserved_at'        => now(),
            ]);
        }
    }

    /**
     * Atomically release reservations when order is cancelled or expired per FR-INV-006.
     *
     * @param Order $order
     */
    public function releaseReservations(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $reservations = $order->reservations()
                ->where('status', 'reserved')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $updated = DB::table('product_variants')
                    ->where('id', $reservation->product_variant_id)
                    ->where('stock_reserved', '>=', $reservation->quantity)
                    ->decrement('stock_reserved', $reservation->quantity);

                if ($updated !== 1) {
                    throw new RuntimeException("Reserved stock is inconsistent for product variant [{$reservation->product_variant_id}].");
                }

                $reservation->update([
                    'status'      => 'released',
                    'released_at' => now(),
                ]);
            }
        });
    }

    /**
     * Atomically convert reservation to physical stock deduction upon confirmed payment (FR-INV-004, FR-INV-005).
     *
     * @param Order $order
     */
    public function convertReservationsToPhysicalDeduction(Order $order): void
    {
        $reservations = $order->reservations()->where('status', 'reserved')->get();
        $deductedItems = [];

        foreach ($reservations as $reservation) {
            DB::table('product_variants')
                ->where('id', $reservation->product_variant_id)
                ->decrement('stock_physical', $reservation->quantity);

            DB::table('product_variants')
                ->where('id', $reservation->product_variant_id)
                ->decrement('stock_reserved', $reservation->quantity);

            $reservation->update([
                'status' => 'converted',
            ]);

            $deductedItems[] = [
                'product_variant_id' => $reservation->product_variant_id,
                'quantity'           => $reservation->quantity,
            ];
        }

        if ($deductedItems !== []) {
            AuditEvent::dispatch(
                'stock.deducted',
                $order->id,
                null,
                'system',
                ['items' => $deductedItems]
            );
        }
    }
}

