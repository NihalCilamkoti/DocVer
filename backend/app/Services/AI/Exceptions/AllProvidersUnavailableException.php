<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;
use Throwable;

class AllProvidersUnavailableException extends RuntimeException
{
    /**
     * @param array<string, string> $failedAttempts
     */
    public function __construct(
        public array $failedAttempts = [],
        string $message = 'All configured AI providers failed or are unavailable',
        ?Throwable $previous = null
    ) {
        $details = implode(', ', array_map(
            fn($prov, $err) => "{$prov}: {$err}",
            array_keys($failedAttempts),
            $failedAttempts
        ));

        $msg = $details ? "{$message} ({$details})" : $message;
        parent::__construct($msg, 503, $previous);
    }
}
