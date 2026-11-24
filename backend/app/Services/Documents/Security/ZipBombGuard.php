<?php

namespace App\Services\Documents\Security;

class ZipBombGuard
{
    public const MAX_UNCOMPRESSED_SIZE_BYTES = 150 * 1024 * 1024; // 150 MB maximum uncompressed
    public const MAX_COMPRESSION_RATIO = 100;                     // 100:1 ratio limit
    public const MAX_ENTRIES_COUNT = 1500;                        // Max zip entries

    public function isZipArchive(string $filePath): bool
    {
        if (! file_exists($filePath) || filesize($filePath) < 4) {
            return false;
        }

        $handle = fopen($filePath, 'rb');
        $magic = fread($handle, 4);
        fclose($handle);

        return str_starts_with($magic, "PK\x03\x04");
    }

    /**
     * Inspect OOXML / Zip container and guard against decompression bombs.
     * Throws \RuntimeException on violation.
     */
    public function inspect(string $filePath): void
    {
        $zip = new \ZipArchive();
        $status = $zip->open($filePath);

        if ($status !== true) {
            throw new \RuntimeException("Unable to open archive for compression inspection.");
        }

        $numFiles = $zip->numFiles;
        if ($numFiles > self::MAX_ENTRIES_COUNT) {
            $zip->close();
            throw new \RuntimeException("Security violation: Archive contains {$numFiles} files, exceeding limit of " . self::MAX_ENTRIES_COUNT);
        }

        $compressedSize = filesize($filePath);
        $totalUncompressedSize = 0;

        for ($i = 0; $i < $numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (! $stat) continue;

            $totalUncompressedSize += $stat['size'];

            if ($totalUncompressedSize > self::MAX_UNCOMPRESSED_SIZE_BYTES) {
                $zip->close();
                throw new \RuntimeException("Security violation: Decompressed size exceeds maximum threshold of 150 MB.");
            }
        }

        $zip->close();

        if ($compressedSize > 0) {
            $ratio = $totalUncompressedSize / $compressedSize;
            if ($ratio > self::MAX_COMPRESSION_RATIO) {
                throw new \RuntimeException("Security violation: Abnormal compression ratio ({$ratio}:1) indicative of compression bomb.");
            }
        }
    }

    public function assertSafe(string $filePath): void
    {
        $this->inspect($filePath);
    }
}
