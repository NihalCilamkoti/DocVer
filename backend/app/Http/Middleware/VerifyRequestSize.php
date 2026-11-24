<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyRequestSize
{
    /**
     * Rejects HTTP payloads exceeding configured maximum payload size (e.g. 50MB).
     */
    public function handle(Request $request, Closure $next, int $maxMegabytes = 50): Response
    {
        $contentLength = (int) $request->header('Content-Length', 0);
        $maxBytes = $maxMegabytes * 1024 * 1024;

        if ($contentLength > $maxBytes) {
            return response()->json([
                'success' => false,
                'message' => "Payload exceeds maximum allowed size of {$maxMegabytes}MB.",
                'error_code' => 'PAYLOAD_TOO_LARGE',
            ], 413);
        }

        return $next($request);
    }
}
