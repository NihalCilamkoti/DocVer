<?php

namespace App\Services\AI\Exceptions;

use Throwable;

class ContextLengthExceededException extends AIProviderException
{
    public function __construct(
        string $provider,
        string $message = 'Maximum model context length exceeded',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $provider, 400, null, $previous);
    }
}
