<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CancellationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BrowserGuestCancellationController extends Controller
{
    public function store(Request $request, string $id, CancellationService $cancellationService)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'guest_email' => ['required', 'email', 'max:255'],
        ]);

        $order = $this->trackedOrder($request, $id);
        $this->assertGuestEmail($order, $data['guest_email']);

        $cancellationService->submitRequest(
            $order,
            null,
            $data['guest_email'],
            $data['reason']
        );

        return redirect()->route('storefront.orders.track')->with('status', 'Permintaan pembatalan dikirim untuk ditinjau oleh toko.');
    }

    public function withdraw(Request $request, string $id, int $requestId, CancellationService $cancellationService)
    {
        $data = $request->validate([
            'guest_email' => ['required', 'email', 'max:255'],
        ]);

        $order = $this->trackedOrder($request, $id);
        $this->assertGuestEmail($order, $data['guest_email']);

        $cancellationService->withdrawRequest(
            $order,
            $requestId,
            null,
            $data['guest_email']
        );

        return redirect()->route('storefront.orders.track')->with('status', 'Permintaan pembatalan ditarik.');
    }

    private function trackedOrder(Request $request, string $orderId): Order
    {
        $trackedOrderId = (string) $request->session()->get('tracking.order_id', '');
        abort_unless($trackedOrderId !== '' && hash_equals($trackedOrderId, $orderId), 404);

        return Order::query()->whereKey($orderId)->whereNull('customer_id')->firstOrFail();
    }

    private function assertGuestEmail(Order $order, string $email): void
    {
        if (!$order->guest_email || !hash_equals(strtolower(trim($order->guest_email)), strtolower(trim($email)))) {
            throw ValidationException::withMessages([
                'guest_email' => 'Email tidak cocok dengan pesanan ini.',
            ]);
        }
    }
}
