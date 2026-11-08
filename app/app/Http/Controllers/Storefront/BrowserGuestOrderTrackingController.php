<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\GuestOrderTrackingService;
use Illuminate\Http\Request;

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
                ->with(['items', 'payment', 'shipment', 'refund'])
                ->first()
            : null;

        return view('storefront.tracking.index', compact('order'));
    }

    public function store(Request $request, GuestOrderTrackingService $trackingService)
    {
        $request->session()->forget('tracking.order_id');

        $data = $request->validate([
            'order_id' => ['required', 'string', 'size:26'],
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
