<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetryPaymentSessionController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        // Authorization check
        $user = $request->user();
        if ($order->customer_id) {
            if (!$user || $user->id !== $order->customer_id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        } else {
            // Guest verification
            $email = $request->input('guest_email');
            if (empty($email) || strtolower($email) !== strtolower((string) $order->guest_email)) {
                return response()->json(['message' => 'Guest email does not match order record.'], 403);
            }
        }

        if ($order->status !== OrderStatus::PendingPayment) {
            return response()->json([
                'message' => "Cannot create payment session for order in state [{$order->status->value}].",
            ], 422);
        }

        $session = $this->paymentService->createPaymentSession($order);

        if (!$session) {
            return response()->json([
                'message' => 'Unable to create payment session with gateway at this time. Please try again.',
            ], 502);
        }

        return response()->json([
            'message' => 'Payment session generated successfully.',
            'data'    => [
                'order_id'    => $order->id,
                'payment_url' => $session['payment_url'],
                'expires_at'  => $session['expires_at'],
            ],
        ]);
    }
}
