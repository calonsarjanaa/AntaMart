<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use Exception;

class InvalidOrderStateException extends Exception
{
    public function __construct(
        public readonly OrderStatus $fromState,
        public readonly OrderStatus $toState,
        string $message = ""
    ) {
        $msg = $message ?: "Cannot transition order from state '{$fromState->value}' to '{$toState->value}'.";
        parent::__construct($msg, 422);
    }
}

