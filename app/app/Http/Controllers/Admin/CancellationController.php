<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancellationController extends Controller
{
    public function __construct(
        protected CancellationService $cancellationService
    ) {}

    public function review(Request $request, string $id, int $requestId): JsonResponse
    {
        $data = $request->validate([
            'decision'        => ['required', 'in:approve,reject'],
            'rejection_reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ]);

        $cancellationRequest = $this->cancellationService->reviewRequest(
            Order::findOrFail($id),
            $requestId,
            $request->user(),
            $data['decision'],
            $data['rejection_reason'] ?? null
        );

        return response()->json([
            'message' => $data['decision'] === 'approve'
                ? 'Cancellation request approved.'
                : 'Cancellation request rejected.',
            'data'    => [
                'id'               => $cancellationRequest->id,
                'order_id'         => $cancellationRequest->order_id,
                'status'           => $cancellationRequest->status->value,
                'rejection_reason' => $cancellationRequest->rejection_reason,
                'decided_at'       => $cancellationRequest->decided_at?->toIso8601String(),
            ],
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $cancellationRequest = $this->cancellationService->cancelByOwner(
            Order::findOrFail($id),
            $request->user(),
            $data['reason']
        );

        return response()->json([
            'message' => 'Order cancelled by the Store Owner.',
            'data'    => [
                'order_id'            => $cancellationRequest->order_id,
                'status'              => $cancellationRequest->status->value,
                'cancellation_reason' => $cancellationRequest->reason,
                'refund_status'       => $cancellationRequest->order->refund?->status->value,
            ],
        ]);
    }
}
