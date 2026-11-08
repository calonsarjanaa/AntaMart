<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CancellationStatus;
use App\Http\Controllers\Controller;
use App\Models\CancellationRequest;
use App\Models\Order;
use App\Services\CancellationService;
use Illuminate\Http\Request;

class OwnerCancellationController extends Controller
{
    public function index()
    {
        $requests = CancellationRequest::query()
            ->with(['order.customer', 'requester'])
            ->where('status', CancellationStatus::Pending->value)
            ->whereNull('decided_at')
            ->whereHas('order', fn ($orders) => $orders->where('status', 'processing'))
            ->oldest()
            ->paginate(20);

        return view('owner.cancellations.index', compact('requests'));
    }

    public function review(Request $request, string $orderId, int $requestId, CancellationService $cancellationService)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ]);

        $cancellationService->reviewRequest(
            Order::findOrFail($orderId),
            $requestId,
            $request->user('web'),
            $data['decision'],
            $data['rejection_reason'] ?? null
        );

        return to_route('owner.orders.show', $orderId)->with('status', $data['decision'] === 'approve'
            ? 'Permintaan pembatalan disetujui.'
            : 'Permintaan pembatalan ditolak.');
    }

    public function cancelOrder(Request $request, string $orderId, CancellationService $cancellationService)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $cancellationService->cancelByOwner(
            Order::findOrFail($orderId),
            $request->user('web'),
            $data['reason']
        );

        return to_route('owner.orders.show', $orderId)->with('status', 'Pesanan dibatalkan oleh pemilik toko.');
    }
}
