<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancellationRequestController extends Controller
{
    public function __construct(
        protected CancellationService $cancellationService
    ) {}

    public function store(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason'      => ['required', 'string', 'max:2000'],
            'guest_email' => ['nullable', 'email', 'max:255'],
        ]);

        $cancellationRequest = $this->cancellationService->submitRequest(
            Order::findOrFail($id),
            $request->user('sanctum'),
            $data['guest_email'] ?? null,
            $data['reason']
        );

        return response()->json([
            'message' => 'Cancellation request submitted for review.',
            'data'    => [
                'id'         => $cancellationRequest->id,
                'order_id'   => $cancellationRequest->order_id,
                'status'     => $cancellationRequest->status->value,
                'reason'     => $cancellationRequest->reason,
                'created_at' => $cancellationRequest->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function withdraw(Request $request, string $id, int $requestId): JsonResponse
    {
        $data = $request->validate([
            'guest_email' => ['nullable', 'email', 'max:255'],
        ]);

        $cancellationRequest = $this->cancellationService->withdrawRequest(
            Order::findOrFail($id),
            $requestId,
            $request->user('sanctum'),
            $data['guest_email'] ?? null
        );

        return response()->json([
            'message' => 'Cancellation request withdrawn.',
            'data'    => [
                'id'           => $cancellationRequest->id,
                'order_id'     => $cancellationRequest->order_id,
                'status'       => $cancellationRequest->status->value,
                'withdrawn_at' => $cancellationRequest->withdrawn_at?->toIso8601String(),
            ],
        ]);
    }
}
