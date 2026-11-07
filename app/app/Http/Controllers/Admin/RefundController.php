<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    public function markRefunded(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'notes'                 => ['nullable', 'string', 'max:5000'],
            'resellable_item_ids'   => ['sometimes', 'array'],
            'resellable_item_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $refund = $this->refundService->markRefunded(
            Order::findOrFail($id),
            $request->user(),
            $data['notes'] ?? null,
            $data['resellable_item_ids'] ?? []
        );

        return response()->json([
            'message' => 'Refund marked as refunded.',
            'data'    => [
                'order_id'            => $refund->order_id,
                'amount'              => $refund->amount,
                'status'              => $refund->status->value,
                'refunded_at'         => $refund->refunded_at?->toIso8601String(),
                'refunded_by'         => $refund->refunded_by,
                'notes'               => $refund->notes,
                'resellable_item_ids' => $refund->resellable_item_ids ?? [],
            ],
        ]);
    }
}
