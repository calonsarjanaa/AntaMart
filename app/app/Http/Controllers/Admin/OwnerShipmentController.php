<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ShipmentService;
use Illuminate\Http\Request;

class OwnerShipmentController extends Controller
{
    public function store(Request $request, string $orderId, ShipmentService $shipmentService)
    {
        $data = $request->validate([
            'tracking_number' => ['required', 'string', 'max:255'],
            'tracking_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $shipmentService->createManualShipment(
            Order::findOrFail($orderId),
            $request->user('web'),
            $data['tracking_number'],
            $data['tracking_url'] ?? null
        );

        return to_route('owner.orders.show', $orderId)
            ->with('status', 'Pengiriman manual berhasil dicatat.');
    }

    public function confirmDelivery(Request $request, string $orderId, ShipmentService $shipmentService)
    {
        $shipmentService->confirmDelivery(
            Order::with('payment')->findOrFail($orderId),
            $request->user('web'),
            $request->boolean('cash_received')
        );

        return to_route('owner.orders.show', $orderId)
            ->with('status', 'Pesanan ditandai telah diterima.');
    }
}
