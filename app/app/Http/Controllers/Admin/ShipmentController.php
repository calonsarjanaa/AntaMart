<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(
        protected ShipmentService $shipmentService
    ) {}

    public function store(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'tracking_number' => ['required', 'string', 'max:255'],
            'tracking_url'    => ['nullable', 'url', 'max:2048'],
        ]);

        $shipment = $this->shipmentService->createManualShipment(
            Order::findOrFail($id),
            $request->user(),
            $data['tracking_number'],
            $data['tracking_url'] ?? null
        );

        return response()->json([
            'message' => 'Shipment created.',
            'data'    => [
                'order_id'        => $shipment->order_id,
                'method'          => $shipment->method,
                'tracking_number' => $shipment->tracking_number,
                'tracking_url'    => $shipment->tracking_url,
                'shipped_at'      => $shipment->shipped_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function confirmDelivery(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'cash_received' => ['sometimes', 'boolean'],
        ]);
        $order = Order::with('payment')->findOrFail($id);
        $shipment = $this->shipmentService->confirmDelivery(
            $order,
            $request->user(),
            $request->boolean('cash_received')
        );

        return response()->json([
            'message' => 'Delivery confirmed.',
            'data'    => [
                'order_id'     => $shipment->order_id,
                'delivered_at' => $shipment->delivered_at?->toIso8601String(),
                'payment_status' => $order->payment()->value('status'),
            ],
        ]);
    }
}
