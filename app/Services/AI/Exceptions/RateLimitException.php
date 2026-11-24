<?php

namespace App\Services\AI\Exceptions;

use Throwable;

class RateLimitException extends AIProviderException
{
    public function __construct(
        string $provider,
        string $message = 'Rate limit exceeded (HTTP 429)',
        ?int $retryAfterSeconds = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $provider, 429, $retryAfterSeconds, $previous);
    }
}
