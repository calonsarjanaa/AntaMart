<?php

namespace App\Services;

use App\Enums\CancellationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\AuditEvent;
use App\Models\CancellationRequest as CancellationRequestModel;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancellationService
{
    public function __construct(
        protected RefundService $refundService,
        protected InventoryService $inventoryService
    ) {}

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

            $cancellationRequest = $order->cancellationRequests()->create([
                'requested_by' => $user?->id,
                'reason'       => $reason,
                'status'       => CancellationStatus::Pending,
            ]);

            AuditEvent::dispatch(
                'cancellation.requested',
                $order->id,
                $user?->id,
                $user?->role ?? 'guest',
                ['cancellation_request_id' => $cancellationRequest->id]
            );

            return $cancellationRequest;
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

            AuditEvent::dispatch(
                'cancellation.withdrawn',
                $order->id,
                $user?->id,
                $user?->role ?? 'guest',
                ['cancellation_request_id' => $cancellationRequest->id]
            );

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
                $this->cancelProcessingOrder($order, $cancellationRequest->reason, $owner);
                $decisionData['status'] = CancellationStatus::Approved;
            } else {
                $decisionData['status'] = CancellationStatus::Rejected;
                $decisionData['rejection_reason'] = $rejectionReason;
            }

            $cancellationRequest->update($decisionData);

            AuditEvent::dispatch(
                $decision === 'approve' ? 'cancellation.approved' : 'cancellation.rejected',
                $order->id,
                $owner->id,
                $owner->role,
                ['cancellation_request_id' => $cancellationRequest->id]
            );

            return $cancellationRequest->fresh();
        });
    }

    public function cancelByOwner(Order $order, User $owner, string $reason): CancellationRequestModel
    {
        return DB::transaction(function () use ($order, $owner, $reason): CancellationRequestModel {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->cancelProcessingOrder($order, $reason, $owner);

            $now = now();

            $pendingRequests = $order->cancellationRequests()
                ->where('status', CancellationStatus::Pending->value)
                ->whereNull('decided_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($pendingRequests as $pendingRequest) {
                $pendingRequest->update([
                    'status'     => CancellationStatus::Approved->value,
                    'decided_by' => $owner->id,
                    'decided_at' => $now,
                ]);

                AuditEvent::dispatch(
                    'cancellation.approved',
                    $order->id,
                    $owner->id,
                    $owner->role,
                    ['cancellation_request_id' => $pendingRequest->id]
                );
            }

            $cancellationRequest = $order->cancellationRequests()->create([
                'requested_by' => null,
                'reason'       => $reason,
                'status'       => CancellationStatus::Approved,
                'decided_by'   => $owner->id,
                'decided_at'   => $now,
            ]);

            AuditEvent::dispatch(
                'cancellation.approved',
                $order->id,
                $owner->id,
                $owner->role,
                [
                    'cancellation_request_id' => $cancellationRequest->id,
                    'owner_initiated'          => true,
                ]
            );

            return $cancellationRequest;
        });
    }

    private function cancelProcessingOrder(Order $order, string $reason, User $actor): void
    {
        if ($order->status !== OrderStatus::Processing) {
            throw ValidationException::withMessages([
                'order' => 'Only processing orders can be cancelled.',
            ]);
        }

        $payment = $order->payment()->lockForUpdate()->first();
        $order->transitionTo(OrderStatus::Cancelled);

        if ($payment?->status === PaymentStatus::Paid) {
            $this->refundService->flagForOrder($order, "Order cancellation: {$reason}", $actor);
        } elseif ($payment?->payment_method === PaymentMethod::CashOnDelivery) {
            $this->inventoryService->restockConvertedReservations($order, $actor->id, $actor->role);
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
