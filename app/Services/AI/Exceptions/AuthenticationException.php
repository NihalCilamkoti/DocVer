<?php

namespace App\Services\AI\Exceptions;

use Throwable;

class AuthenticationException extends AIProviderException
{
    public function __construct(
        string $provider,
        string $message = 'Invalid API key or unauthorized access (HTTP 401)',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $provider, 401, null, $previous);
    }
}
