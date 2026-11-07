<?php

namespace App\Http\Middleware;

use App\Adapters\Payment\PaymentGatewayInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyXenditWebhook
{
    public function __construct(
        protected PaymentGatewayInterface $paymentGateway
    ) {}

    /**
     * Handle an incoming request and verify the Xendit callback token.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headers = $request->headers->all();
        $payload = $request->all();

        if (!$this->paymentGateway->verifyWebhook($headers, $payload)) {
            return response()->json([
                'message' => 'Invalid or missing webhook verification token.',
            ], 401);
        }

        return $next($request);
    }
}

