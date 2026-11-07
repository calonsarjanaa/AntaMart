<?php

namespace App\Http\Controllers\Webhook;

use App\Adapters\Payment\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessXenditCallback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class XenditController extends Controller
{
    public function __construct(
        protected PaymentGatewayInterface $paymentGateway
    ) {}

    /**
     * Handle incoming Xendit webhook callback.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $parsed = $this->paymentGateway->parseCallback($request->all());

        // Process callback via job
        ProcessXenditCallback::dispatchSync($parsed);

        return response()->json([
            'status' => 'acknowledged',
        ], 200);
    }
}
