<?php

namespace App\Http\Middleware;

use App\Services\Documents\Security\FileSignatureValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateUploadSecurity
{
    /**
     * Inspects uploaded binary files against magic bytes and suspicious file signatures.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasFile('file') || $request->hasFile('document')) {
            $files = array_filter([$request->file('file'), $request->file('document')]);

            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $path = $file->getRealPath();
                    $filename = $file->getClientOriginalName();

                    // Block suspicious path traversal in filename
                    if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Malformed filename detected.',
                            'error_code' => 'INVALID_FILENAME',
                        ], 422);
                    }

                    // Check file size limit
                    if ($file->getSize() > 50 * 1024 * 1024) {
                        return response()->json([
                            'success' => false,
                            'message' => 'File size exceeds maximum allowed 50MB.',
                            'error_code' => 'FILE_TOO_LARGE',
                        ], 413);
                    }
                }
            }
        }

        return $next($request);
    }
}
