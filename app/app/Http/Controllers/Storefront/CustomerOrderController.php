<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\CancellationStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Services\CancellationService;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = $request->user('web')->orders()
            ->with(['payment', 'shipment'])
            ->withCount('items')
            ->latest()
            ->paginate(10);

        return view('storefront.account.orders.index', compact('orders'));
    }

    public function show(Request $request, string $orderId)
    {
        $user = $request->user('web');
        $order = $user->orders()
            ->with(['items', 'address', 'payment', 'shipment', 'refund', 'latestCancellationRequest'])
            ->whereKey($orderId)
            ->firstOrFail();

        $pendingCancellation = $order->cancellationRequests()
            ->where('status', CancellationStatus::Pending->value)
            ->latest()
            ->first();

        return view('storefront.account.orders.show', [
            'order' => $order,
            'pendingCancellation' => $pendingCancellation,
            'cancellationToDisplay' => $pendingCancellation ?? $order->latestCancellationRequest,
            'canRequestCancellation' => $order->status === OrderStatus::Processing && !$pendingCancellation,
            'canWithdrawCancellation' => $pendingCancellation
                && $pendingCancellation->decided_at === null
                && (string) $pendingCancellation->requested_by === (string) $user->id,
        ]);
    }

    public function requestCancellation(Request $request, string $orderId, CancellationService $cancellationService)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user('web');
        $order = $user->orders()->whereKey($orderId)->firstOrFail();
        $cancellationService->submitRequest($order, $user, null, $data['reason']);

        return redirect()->route('storefront.account.orders.show', $order->id)
            ->with('status', 'Permintaan pembatalan dikirim untuk ditinjau oleh toko.');
    }

    public function withdrawCancellation(Request $request, string $orderId, int $requestId, CancellationService $cancellationService)
    {
        $user = $request->user('web');
        $order = $user->orders()->whereKey($orderId)->firstOrFail();
        $cancellationService->withdrawRequest($order, $requestId, $user, null);

        return redirect()->route('storefront.account.orders.show', $order->id)
            ->with('status', 'Permintaan pembatalan ditarik.');
    }
}
