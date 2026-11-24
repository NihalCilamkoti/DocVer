<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;
use Throwable;

class AIProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public string $provider = 'unknown',
        public int $httpStatus = 0,
        public ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct("[{$provider}] {$message}", $httpStatus, $previous);
    }
}
