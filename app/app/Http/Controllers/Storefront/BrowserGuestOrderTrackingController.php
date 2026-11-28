<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Enums\CancellationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\CancellationService;
use App\Services\GuestOrderTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class BrowserGuestOrderTrackingController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('new')) {
            $request->session()->forget('tracking.order_id');
        }

        $orderId = $request->session()->get('tracking.order_id');
        $order = $orderId
            ? Order::query()
                ->whereKey($orderId)
                ->whereNull('customer_id')
                ->with(['items', 'payment', 'shipment', 'refund', 'latestCancellationRequest'])
                ->first()
            : null;

        $pendingCancellation = $order?->cancellationRequests()
            ->where('status', CancellationStatus::Pending->value)
            ->latest()
            ->first();
        $hasPendingCancellation = $pendingCancellation !== null;
        $cancellationAttempts = $order ? $order->cancellationRequests()->count() : 0;
        $maxCancellationAttempts = CancellationService::MAX_CUSTOMER_REQUESTS;
        $isPaymentPending = !$order?->payment || $order->payment->status === PaymentStatus::Pending;

        $canDirectCancel = $order && (
            $order->status === OrderStatus::AwaitingConfirmation
            || ($order->status === OrderStatus::PendingPayment && $isPaymentPending)
        );
        $canRequestApprovalCancel = $order
            && in_array($order->status, [OrderStatus::Processing, OrderStatus::Shipped], true)
            && !$hasPendingCancellation
            && $cancellationAttempts < $maxCancellationAttempts;

        return view('storefront.tracking.index', [
            'order' => $order,
            'pendingCancellation' => $pendingCancellation,
            'latestCancellation' => $order?->latestCancellationRequest,
            'canDirectCancel' => $canDirectCancel,
            'canRequestCancellation' => $canDirectCancel || $canRequestApprovalCancel,
            'cancellationAttempts' => $cancellationAttempts,
            'maxCancellationAttempts' => $maxCancellationAttempts,
            'cancellationStoreUrl' => ($canDirectCancel || $canRequestApprovalCancel)
                ? URL::temporarySignedRoute('storefront.orders.cancellation.store', now()->addDays(3), ['id' => $order->id])
                : null,
            'cancellationWithdrawUrl' => $pendingCancellation && $pendingCancellation->decided_at === null
                ? URL::temporarySignedRoute('storefront.orders.cancellation.withdraw', now()->addDays(3), [
                    'id' => $order->id,
                    'requestId' => $pendingCancellation->id,
                ])
                : null,
        ]);
    }

    public function store(Request $request, GuestOrderTrackingService $trackingService)
    {
        $request->session()->forget('tracking.order_id');

        $data = $request->validate([
            'order_id' => ['required', 'string', 'min:10', 'max:26'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $order = $trackingService->find($data['order_id'], $data['email']);

        if (!$order) {
            return redirect()->route('storefront.orders.track')
                ->withInput()
                ->withErrors(['lookup' => 'Pesanan tidak ditemukan. Periksa kembali ID pesanan dan email checkout.']);
        }

        $request->session()->put('tracking.order_id', $order->id);

        return redirect()->route('storefront.orders.track');
    }
}
