<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\AuditEvent;
use App\Jobs\ExpireUnpaidOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderConfirmationService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected PaymentService $paymentService
    ) {}

    /**
     * Owner accepts an awaiting_confirmation order after checking physical stock.
     * - COD: transitions to processing and deducts physical stock immediately.
     * - QRIS/Xendit: transitions to pending_payment (60 min deadline) and generates Xendit QRIS invoice.
     */
    public function accept(Order $order, User $owner): Order
    {
        $isXendit = false;

        $acceptedOrder = DB::transaction(function () use ($order, $owner, &$isXendit): Order {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== OrderStatus::AwaitingConfirmation) {
                throw ValidationException::withMessages([
                    'order' => 'Hanya pesanan berstatus Menunggu Konfirmasi yang dapat diterima.',
                ]);
            }

            $payment = $lockedOrder->payment()->lockForUpdate()->first();
            $paymentMethod = $payment?->payment_method ?? PaymentMethod::Xendit;

            if ($paymentMethod === PaymentMethod::CashOnDelivery) {
                $lockedOrder->confirmed_at = now();
                $lockedOrder->transitionTo(OrderStatus::Processing);

                $this->inventoryService->convertReservationsToPhysicalDeduction($lockedOrder);

                AuditEvent::dispatch(
                    'order.accepted',
                    $lockedOrder->id,
                    $owner->id,
                    $owner->role,
                    ['payment_method' => 'cod']
                );

                AuditEvent::dispatch(
                    'payment.cod_order_created',
                    $lockedOrder->id,
                    $owner->id,
                    $owner->role,
                    ['amount' => $lockedOrder->total]
                );

                return $lockedOrder;
            }

            // QRIS / Xendit path: open 60-minute payment window
            $isXendit = true;
            $durationSeconds = max(60, (int) config('services.xendit.invoice_duration', 3600));
            $expiresAt = now()->addSeconds($durationSeconds);

            $lockedOrder->confirmed_at = now();
            $lockedOrder->expires_at = $expiresAt;
            $lockedOrder->transitionTo(OrderStatus::PendingPayment);

            if ($payment) {
                $payment->expired_at = $expiresAt;
                $payment->save();
            }

            ExpireUnpaidOrder::dispatch($lockedOrder->id)
                ->onConnection('database')
                ->delay($expiresAt);

            AuditEvent::dispatch(
                'order.accepted',
                $lockedOrder->id,
                $owner->id,
                $owner->role,
                [
                    'payment_method' => 'xendit',
                    'expires_at'     => $expiresAt->toIso8601String(),
                ]
            );

            return $lockedOrder;
        });

        // Create Xendit QRIS invoice outside the DB lock (resilient: if gateway fails, order stays pending_payment & can retry)
        if ($isXendit) {
            $this->paymentService->createPaymentSession($acceptedOrder);
        }

        return $acceptedOrder->fresh(['items', 'address', 'payment']);
    }

    /**
     * Owner rejects an awaiting_confirmation order with a visible reason for the customer.
     * Releases all reserved stock immediately (zero refund needed since payment has not occurred).
     */
    public function reject(Order $order, User $owner, string $reason): Order
    {
        return DB::transaction(function () use ($order, $owner, $reason): Order {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== OrderStatus::AwaitingConfirmation) {
                throw ValidationException::withMessages([
                    'order' => 'Hanya pesanan berstatus Menunggu Konfirmasi yang dapat ditolak.',
                ]);
            }

            $lockedOrder->rejection_reason = trim($reason);
            $lockedOrder->rejected_at = now();
            $lockedOrder->transitionTo(OrderStatus::Cancelled);

            $payment = $lockedOrder->payment()->lockForUpdate()->first();
            if ($payment && $payment->status === PaymentStatus::Pending) {
                $payment->status = PaymentStatus::Expired;
                $payment->save();
            }

            $this->inventoryService->releaseReservations($lockedOrder);

            AuditEvent::dispatch(
                'order.rejected',
                $lockedOrder->id,
                $owner->id,
                $owner->role,
                ['reason' => $lockedOrder->rejection_reason]
            );

            return $lockedOrder->fresh(['items', 'address', 'payment']);
        });
    }
}

