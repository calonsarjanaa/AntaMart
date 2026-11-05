<?php

namespace App\Exceptions;

use Exception;

class InvalidPaymentCallbackException extends Exception
{
    public function __construct(
        string $message = "The payment callback failed security or payload validation.",
        int $code = 400
    ) {
        parent::__construct($message, $code);
    }
}

