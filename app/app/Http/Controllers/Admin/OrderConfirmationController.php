<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderConfirmationController extends Controller
{
    public function __construct(
        protected OrderConfirmationService $confirmationService
    ) {}

    public function accept(Request $request, string $id): JsonResponse
    {
        $order = $this->confirmationService->accept(
            Order::findOrFail($id),
            $request->user()
        );

        return response()->json([
            'message' => 'Order accepted.',
            'data'    => [
                'order_id'       => $order->id,
                'status'         => $order->status->value,
                'payment_method' => $order->payment?->payment_method?->value,
                'payment_url'    => $order->payment?->xendit_payment_url,
                'expires_at'     => $order->expires_at?->toIso8601String(),
                'confirmed_at'   => $order->confirmed_at?->toIso8601String(),
            ],
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $order = $this->confirmationService->reject(
            Order::findOrFail($id),
            $request->user(),
            $data['rejection_reason']
        );

        return response()->json([
            'message' => 'Order rejected.',
            'data'    => [
                'order_id'         => $order->id,
                'status'           => $order->status->value,
                'rejection_reason' => $order->rejection_reason,
                'rejected_at'      => $order->rejected_at?->toIso8601String(),
            ],
        ]);
    }
}

