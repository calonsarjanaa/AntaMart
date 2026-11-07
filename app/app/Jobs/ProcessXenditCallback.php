<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Services\InventoryService;
use App\Services\RefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessXenditCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array{
     *     payment_id: string,
     *     order_id: string,
     *     status: string,
     *     amount: float,
     *     raw: array<string, mixed>
     * } $callbackData
     */
    public function __construct(
        public array $callbackData
    ) {}

    public function handle(InventoryService $inventoryService, RefundService $refundService): void
    {
        $paymentId = $this->callbackData['payment_id'];
        $orderId   = $this->callbackData['order_id'];
        $status    = strtoupper($this->callbackData['status']);
        $amount    = (float) $this->callbackData['amount'];
        $raw       = $this->callbackData['raw'];

        // FR-PAY-005 — Idempotency check: duplicate callback received multiple times
        if (PaymentCallback::where('xendit_payment_id', $paymentId)->exists()) {
            Log::info("Duplicate Xendit callback ignored for payment [{$paymentId}].");
            return;
        }

        $order = Order::find($orderId);
        if (!$order) {
            Log::warning("Xendit callback received for non-existent order [{$orderId}].", ['payment_id' => $paymentId]);
            return;
        }

        // TBD-05 — Financial integrity check: reject/flag partial payment
        if ($amount < (float) $order->total) {
            Log::error("Partial payment anomaly detected for order [{$orderId}]. Expected: {$order->total}, Received: {$amount}.", [
                'order_id'   => $orderId,
                'payment_id' => $paymentId,
            ]);

            PaymentCallback::create([
                'xendit_payment_id' => $paymentId,
                'order_id'          => $order->id,
                'event_type'        => 'payment.partial_anomaly',
                'payload'           => array_merge($raw, ['error' => 'amount_mismatch']),
                'processed_at'      => now(),
            ]);

            return;
        }

        $isSuccessfulPayment = in_array($status, ['SUCCEEDED', 'PAID', 'SETTLED'], true);
        if (!$isSuccessfulPayment) {
            Log::info("Non-successful Xendit callback status [{$status}] for order [{$orderId}].");
            return;
        }

        DB::transaction(function () use ($paymentId, $order, $amount, $raw, $inventoryService, $refundService) {
            // Serialize payment confirmation with expiry so only one path can release or consume stock.
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

            if (!$lockedOrder || PaymentCallback::where('xendit_payment_id', $paymentId)->exists()) {
                return;
            }

            // Record processed callback
            PaymentCallback::create([
                'xendit_payment_id' => $paymentId,
                'order_id'          => $lockedOrder->id,
                'event_type'        => 'payment.succeeded',
                'payload'           => $raw,
                'processed_at'      => now(),
            ]);

            $payment = Payment::firstOrNew(['order_id' => $lockedOrder->id]);
            $payment->amount = $amount;

            // Scenario 1: Normal Flow — Order is in pending_payment
            if ($lockedOrder->status === OrderStatus::PendingPayment) {
                $payment->status = PaymentStatus::Paid;
                $payment->paid_at = now();
                $payment->save();

                // State transition: pending_payment -> processing
                $lockedOrder->transitionTo(OrderStatus::Processing);

                // FR-INV-004 & FR-INV-005: Convert reservation to physical deduction atomically
                $inventoryService->convertReservationsToPhysicalDeduction($lockedOrder);

                Log::info("Order [{$lockedOrder->id}] successfully transitioned to processing via Xendit payment [{$paymentId}].");
                return;
            }

            // Scenario 2: Late Payment Reconciliation per FR-PAY-006 & REF-BR-05
            // If payment is received after order expired: order remains expired, payment is logged, manual refund is flagged
            if ($lockedOrder->status === OrderStatus::Expired) {
                $payment->status = PaymentStatus::Paid;
                $payment->paid_at = now();
                $payment->late_payment_detected_at = now();
                $payment->save();

                $refundService->flagForOrder($lockedOrder, 'Late payment received after order had expired.');

                Log::warning("Late payment detected for expired order [{$lockedOrder->id}]. Flagged for manual refund.", [
                    'order_id'   => $lockedOrder->id,
                    'payment_id' => $paymentId,
                    'amount'     => $amount,
                ]);
            }
        });
    }
}

