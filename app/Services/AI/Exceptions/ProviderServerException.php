<?php

namespace App\Services\AI\Exceptions;

use Throwable;

class ProviderServerException extends AIProviderException
{
    public function __construct(
        string $provider,
        string $message = 'Upstream AI provider internal server error (HTTP 5xx)',
        int $status = 500,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $provider, $status, null, $previous);
    }
}
