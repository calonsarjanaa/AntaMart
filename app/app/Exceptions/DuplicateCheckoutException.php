<?php

namespace App\Exceptions;

use Exception;

class DuplicateCheckoutException extends Exception
{
    public function __construct(
        public readonly string $idempotencyKey,
        string $message = "A checkout request with this idempotency key has already been processed or is currently in flight."
    ) {
        parent::__construct($message, 409);
    }
}

