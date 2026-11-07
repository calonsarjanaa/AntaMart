<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestOrderTrackingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'string', 'size:26'],
            'email'    => ['required', 'email', 'max:255'],
        ]);

        $order = Order::query()
            ->whereKey($data['order_id'])
            ->whereNull('customer_id')
            ->with(['items', 'payment', 'shipment', 'refund'])
            ->first();

        if (!$order || strtolower(trim((string) $order->guest_email)) !== strtolower(trim($data['email']))) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json([
            'data' => [
                'order_id'       => $order->id,
                'status'         => $order->status->value,
                'payment_status' => $order->payment?->status->value,
                'subtotal'      => $order->subtotal,
                'shipping_cost' => $order->shipping_cost,
                'total'         => $order->total,
                'created_at'    => $order->created_at?->toIso8601String(),
                'items'         => $order->items->map(fn ($item) => [
                    'product_name' => $item->product_snapshot['name'] ?? null,
                    'variant_name' => $item->variant_snapshot['name'] ?? null,
                    'sku'          => $item->sku,
                    'quantity'     => $item->quantity,
                    'unit_price'   => $item->unit_price,
                    'subtotal'     => $item->subtotal,
                ])->values(),
                'shipment'      => $order->shipment ? [
                    'method'          => $order->shipment->method,
                    'tracking_number' => $order->shipment->tracking_number,
                    'tracking_url'    => $order->shipment->tracking_url,
                    'shipped_at'      => $order->shipment->shipped_at?->toIso8601String(),
                    'delivered_at'    => $order->shipment->delivered_at?->toIso8601String(),
                ] : null,
                'refund_status' => $order->refund?->status->value,
            ],
        ]);
    }
}
