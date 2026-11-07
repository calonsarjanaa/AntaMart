<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DuplicateCheckoutException extends Exception
{
    public function __construct(
        public readonly string $idempotencyKey,
        string $message = "A checkout request with this idempotency key has already been processed or is currently in flight."
    ) {
        parent::__construct($message, 409);
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 409);
    }
}
