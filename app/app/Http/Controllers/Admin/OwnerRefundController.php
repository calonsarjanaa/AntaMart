<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RefundService;
use Illuminate\Http\Request;

class OwnerRefundController extends Controller
{
    public function markRefunded(Request $request, string $orderId, RefundService $refundService)
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
            'resellable_item_ids' => ['sometimes', 'array'],
            'resellable_item_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $refundService->markRefunded(
            Order::findOrFail($orderId),
            $request->user('web'),
            $data['notes'] ?? null,
            $data['resellable_item_ids'] ?? []
        );

        return to_route('owner.orders.show', $orderId)
            ->with('status', 'Catatan pengembalian dana selesai telah disimpan.');
    }
}
