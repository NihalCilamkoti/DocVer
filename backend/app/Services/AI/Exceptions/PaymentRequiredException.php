<?php

namespace App\Services\AI\Exceptions;

use Throwable;

class PaymentRequiredException extends AIProviderException
{
    public function __construct(
        string $provider,
        string $message = 'Insufficient provider credits or payment required (HTTP 402)',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $provider, 402, null, $previous);
    }
}
