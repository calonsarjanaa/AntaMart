<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\CancellationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Services\CancellationService;
use App\Services\OrderExpiryService;
use App\Services\PaymentService;
use App\Services\WhatsAppNotificationService;
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

    public function show(
        Request $request,
        string $orderId,
        OrderExpiryService $orderExpiryService,
        WhatsAppNotificationService $whatsAppService
    ) {
        $user = $request->user('web');
        $order = $user->orders()
            ->with(['items', 'address', 'payment', 'shipment', 'refund', 'latestCancellationRequest'])
            ->whereKey($orderId)
            ->firstOrFail();

        if ($orderExpiryService->expireOrder($order)) {
            $order->refresh()->load(['items', 'address', 'payment', 'shipment', 'refund', 'latestCancellationRequest']);
        }

        $pendingCancellation = $order->cancellationRequests()
            ->where('status', CancellationStatus::Pending->value)
            ->latest()
            ->first();

        $cancellationAttempts = $order->cancellationRequests()->count();
        $maxCancellationAttempts = CancellationService::MAX_CUSTOMER_REQUESTS;
        $isPaymentPending = !$order->payment || $order->payment->status === PaymentStatus::Pending;
        $canDirectCancel = $order->status === OrderStatus::AwaitingConfirmation
            || ($order->status === OrderStatus::PendingPayment && $isPaymentPending);
        $canRequestApprovalCancel = in_array($order->status, [OrderStatus::Processing, OrderStatus::Shipped], true)
            && !$pendingCancellation
            && $cancellationAttempts < $maxCancellationAttempts;

        return view('storefront.account.orders.show', [
            'order' => $order,
            'storeWhatsApp' => $whatsAppService->buildCustomerToOwnerNotification($order),
            'pendingCancellation' => $pendingCancellation,
            'cancellationToDisplay' => $pendingCancellation ?? $order->latestCancellationRequest,
            'canDirectCancel' => $canDirectCancel,
            'canRequestCancellation' => $canDirectCancel || $canRequestApprovalCancel,
            'cancellationAttempts' => $cancellationAttempts,
            'maxCancellationAttempts' => $maxCancellationAttempts,
            'canWithdrawCancellation' => $pendingCancellation
                && $pendingCancellation->decided_at === null
                && (string) $pendingCancellation->requested_by === (string) $user->id,
        ]);
    }

    public function pay(
        Request $request,
        string $orderId,
        PaymentService $paymentService,
        OrderExpiryService $orderExpiryService
    ) {
        $user = $request->user('web');
        $order = $user->orders()->with('payment')->whereKey($orderId)->firstOrFail();

        if ($order->status === OrderStatus::PendingPayment && $order->payment?->xendit_invoice_id) {
            if ($paymentService->syncPaymentStatus($order)) {
                return redirect()->route('storefront.account.orders.show', $order->id)
                    ->with('status', 'Pembayaran pesanan ini sudah dikonfirmasi.');
            }
        }

        if ($order->status === OrderStatus::Cancelled) {
            $reason = $order->rejection_reason ?: $order->latestCancellationRequest?->reason;
            $msg = 'Pesanan ini telah dibatalkan sehingga pembayaran tidak dapat dilanjutkan.'
                . ($reason ? " Alasan: {$reason}" : '');

            return redirect()->route('storefront.account.orders.show', $order->id)
                ->withErrors(['payment' => $msg]);
        }

        if ($order->status === OrderStatus::Expired || ($order->status === OrderStatus::PendingPayment && $order->expires_at?->isPast())) {
            $orderExpiryService->expireOrder($order);

            return redirect()->route('storefront.account.orders.show', $order->id)
                ->withErrors(['payment' => 'Batas waktu pembayaran telah lewat. Pesanan telah kedaluwarsa.']);
        }

        if ($order->status !== OrderStatus::PendingPayment || $order->payment?->status !== PaymentStatus::Pending || empty($order->payment?->xendit_payment_url)) {
            return redirect()->route('storefront.account.orders.show', $order->id)
                ->with('status', 'Status pesanan sudah berubah. Periksa informasi terbaru di halaman ini.');
        }

        return redirect()->away($order->payment->xendit_payment_url);
    }

    public function requestCancellation(Request $request, string $orderId, CancellationService $cancellationService)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user('web');
        $order = $user->orders()->whereKey($orderId)->firstOrFail();
        $cancellationRequest = $cancellationService->submitRequest($order, $user, null, $data['reason']);

        $message = $cancellationRequest->status === CancellationStatus::Approved
            ? 'Pesanan berhasil dibatalkan.'
            : 'Permintaan pembatalan dikirim untuk ditinjau oleh toko.';

        return redirect()->route('storefront.account.orders.show', $order->id)
            ->with('status', $message);
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
