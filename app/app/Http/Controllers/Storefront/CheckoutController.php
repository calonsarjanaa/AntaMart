<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {}

    /**
     * Process checkout for guest or authenticated customer per FR-TRX-001.
     */
    public function __invoke(CheckoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $order = $this->checkoutService->checkout($request->validated(), $user);

        return response()->json([
            'message' => 'Order created successfully.',
            'data'    => [
                'order_id'        => $order->id,
                'status'          => $order->status->value,
                'subtotal'        => $order->subtotal,
                'shipping_cost'   => $order->shipping_cost,
                'total'           => $order->total,
                'idempotency_key' => $order->idempotency_key,
                'items'           => $order->items->map(fn ($item) => [
                    'sku'        => $item->sku,
                    'quantity'   => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal'   => $item->subtotal,
                ]),
                'shipping_address' => [
                    'recipient_name' => $order->address->recipient_name,
                    'phone'          => $order->address->phone,
                    'address_line1'  => $order->address->address_line1,
                    'city'           => $order->address->city,
                    'province'       => $order->address->province,
                    'postal_code'    => $order->address->postal_code,
                ],
                'created_at'      => $order->created_at->toIso8601String(),
            ],
        ], 201);
    }
}
