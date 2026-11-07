<?php

namespace App\Services;

use App\Enums\CancellationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\CancellationRequest as CancellationRequestModel;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancellationService
{
    public function submitRequest(Order $order, ?User $user, ?string $guestEmail, string $reason): CancellationRequestModel
    {
        return DB::transaction(function () use ($order, $user, $guestEmail, $reason): CancellationRequestModel {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->authorizeCustomerOrGuest($order, $user, $guestEmail);

            if ($order->status !== OrderStatus::Processing) {
                throw ValidationException::withMessages([
                    'order' => 'Cancellation can only be requested for processing orders.',
                ]);
            }

            $hasPendingRequest = $order->cancellationRequests()
                ->where('status', CancellationStatus::Pending->value)
                ->exists();

            if ($hasPendingRequest) {
                throw ValidationException::withMessages([
                    'cancellation_request' => 'This order already has a pending cancellation request.',
                ]);
            }

            return $order->cancellationRequests()->create([
                'requested_by' => $user?->id,
                'reason'       => $reason,
                'status'       => CancellationStatus::Pending,
            ]);
        });
    }

    public function withdrawRequest(Order $order, int $requestId, ?User $user, ?string $guestEmail): CancellationRequestModel
    {
        return DB::transaction(function () use ($order, $requestId, $user, $guestEmail): CancellationRequestModel {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $cancellationRequest = $order->cancellationRequests()
                ->whereKey($requestId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeRequestOwner($order, $cancellationRequest, $user, $guestEmail);

            if ($cancellationRequest->decided_at !== null || $cancellationRequest->status !== CancellationStatus::Pending) {
                throw ValidationException::withMessages([
                    'cancellation_request' => 'Only a pending cancellation request can be withdrawn.',
                ]);
            }

            $cancellationRequest->update([
                'status'       => CancellationStatus::Withdrawn,
                'withdrawn_at' => now(),
            ]);

            return $cancellationRequest->fresh();
        });
    }

    public function reviewRequest(
        Order $order,
        int $requestId,
        User $owner,
        string $decision,
        ?string $rejectionReason = null
    ): CancellationRequestModel {
        return DB::transaction(function () use ($order, $requestId, $owner, $decision, $rejectionReason): CancellationRequestModel {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $cancellationRequest = $order->cancellationRequests()
                ->whereKey($requestId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cancellationRequest->status !== CancellationStatus::Pending || $cancellationRequest->decided_at !== null) {
                throw ValidationException::withMessages([
                    'cancellation_request' => 'This cancellation request has already been decided or withdrawn.',
                ]);
            }

            $decisionData = [
                'decided_by' => $owner->id,
                'decided_at' => now(),
            ];

            if ($decision === 'approve') {
                $this->cancelProcessingOrder($order, $cancellationRequest->reason);
                $decisionData['status'] = CancellationStatus::Approved;
            } else {
                $decisionData['status'] = CancellationStatus::Rejected;
                $decisionData['rejection_reason'] = $rejectionReason;
            }

            $cancellationRequest->update($decisionData);

            return $cancellationRequest->fresh();
        });
    }

    public function cancelByOwner(Order $order, User $owner, string $reason): CancellationRequestModel
    {
        return DB::transaction(function () use ($order, $owner, $reason): CancellationRequestModel {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->cancelProcessingOrder($order, $reason);

            $now = now();

            $order->cancellationRequests()
                ->where('status', CancellationStatus::Pending->value)
                ->whereNull('decided_at')
                ->update([
                    'status'     => CancellationStatus::Approved->value,
                    'decided_by' => $owner->id,
                    'decided_at' => $now,
                    'updated_at' => $now,
                ]);

            return $order->cancellationRequests()->create([
                'requested_by' => null,
                'reason'       => $reason,
                'status'       => CancellationStatus::Approved,
                'decided_by'   => $owner->id,
                'decided_at'   => $now,
            ]);
        });
    }

    private function cancelProcessingOrder(Order $order, string $reason): void
    {
        if ($order->status !== OrderStatus::Processing) {
            throw ValidationException::withMessages([
                'order' => 'Only processing orders can be cancelled.',
            ]);
        }

        $payment = $order->payment()->lockForUpdate()->first();
        $order->transitionTo(OrderStatus::Cancelled);

        if ($payment?->status === PaymentStatus::Paid) {
            Refund::firstOrCreate(
                ['order_id' => $order->id],
                [
                    'amount' => $payment->amount,
                    'reason' => "Order cancellation: {$reason}",
                    'status' => RefundStatus::ManualRefundPending,
                ]
            );
        }
    }

    private function authorizeCustomerOrGuest(Order $order, ?User $user, ?string $guestEmail): void
    {
        if ($user) {
            if (!$user->isCustomer() || (string) $order->customer_id !== (string) $user->id) {
                abort(403, 'You are not authorized to cancel this order.');
            }

            return;
        }

        $this->assertGuestEmailMatches($order, $guestEmail);
    }

    private function authorizeRequestOwner(
        Order $order,
        CancellationRequestModel $cancellationRequest,
        ?User $user,
        ?string $guestEmail
    ): void {
        if ($cancellationRequest->requested_by !== null) {
            if (!$user || !$user->isCustomer() || (string) $cancellationRequest->requested_by !== (string) $user->id) {
                abort(403, 'You are not authorized to withdraw this cancellation request.');
            }

            return;
        }

        if ($user !== null) {
            abort(403, 'Guest cancellation requests can only be managed by the guest.');
        }

        $this->assertGuestEmailMatches($order, $guestEmail);
    }

    private function assertGuestEmailMatches(Order $order, ?string $guestEmail): void
    {
        if (!$order->isGuest() || !$guestEmail || !hash_equals(
            strtolower(trim((string) $order->guest_email)),
            strtolower(trim($guestEmail))
        )) {
            abort(403, 'Guest email does not match the order record.');
        }
    }
}
