<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly ?int $productVariantId = null,
        public readonly int $requestedQuantity = 0,
        public readonly int $availableStock = 0,
        string $message = "Insufficient stock available for this product variant."
    ) {
        parent::__construct($message, 422);
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors'  => [
                'stock' => [$this->getMessage()],
            ],
        ], 422);
    }
}
