<?php

namespace App\Services\AI\Exceptions;

use Throwable;

class InvalidStructuredOutputException extends AIProviderException
{
    public function __construct(
        string $provider,
        string $message = 'Model output could not be parsed into valid structured JSON',
        public ?string $rawOutput = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $provider, 422, null, $previous);
    }
}
