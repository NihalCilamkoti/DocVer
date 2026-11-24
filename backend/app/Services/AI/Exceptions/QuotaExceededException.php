<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;
use Throwable;

class QuotaExceededException extends RuntimeException
{
    public function __construct(
        string $message = 'AI usage quota exceeded for user or workspace',
        public string $quotaType = 'daily_user',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 429, $previous);
    }
}
