<?php

namespace App\Services;

use App\Adapters\Payment\PaymentGatewayInterface;
use App\Enums\OrderStatus;
use App\Events\AuditEvent;
use App\Exceptions\InvalidOrderStateException;
use App\Jobs\ExpireUnpaidOrder;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayInterface $paymentGateway
    ) {}

    /**
     * Create a payment gateway session for the given order per FR-PAY-001.
     * Enforces 15-minute expiry per FR-PAY-002 and resilient error handling per TBD-10.
     *
     * @param Order $order
     * @return array{id: string, payment_url: string, expires_at: string}|null
     * @throws InvalidOrderStateException
     */
    public function createPaymentSession(Order $order): ?array
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            throw new InvalidOrderStateException($order->status, OrderStatus::PendingPayment, "Cannot create payment session for order in state [{$order->status->value}].");
        }

        $email = $order->customer?->email ?? $order->guest_email;
        $phone = $order->address?->phone ?? $order->guest_phone;

        try {
            $invoice = $this->paymentGateway->createInvoice(
                orderId: $order->id,
                amount: (float) $order->total,
                customerEmail: $email ?? 'customer@example.com',
                metadata: [
                    'phone' => $phone,
                ]
            );

            $expiresAt = Carbon::parse($invoice['expires_at']);

            DB::transaction(function () use ($order, $invoice, $expiresAt): void {
                // Record gateway session details, audit event, and expiry job atomically.
                $order->update([
                    'expires_at' => $expiresAt,
                ]);

                Payment::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'xendit_invoice_id'  => $invoice['id'],
                        'xendit_payment_url' => $invoice['payment_url'],
                        'amount'             => $order->total,
                        'expired_at'         => $expiresAt,
                    ]
                );

                AuditEvent::dispatch(
                    'payment.session_created',
                    $order->id,
                    $order->customer_id,
                    $order->customer_id ? 'customer' : 'guest',
                    [
                        'invoice_id' => $invoice['id'],
                        'amount'     => $order->total,
                        'expires_at' => $expiresAt->toIso8601String(),
                    ]
                );

                ExpireUnpaidOrder::dispatch($order->id)
                    ->onConnection('database')
                    ->delay($expiresAt);
            });

            return $invoice;
        } catch (Exception $e) {
            // TBD-10 — Log error, order remains pending_payment, client may retry
            Log::error("Payment session creation failed for order [{$order->id}]: " . $e->getMessage(), [
                'order_id'  => $order->id,
                'exception' => $e,
            ]);

            return null;
        }
    }
}

