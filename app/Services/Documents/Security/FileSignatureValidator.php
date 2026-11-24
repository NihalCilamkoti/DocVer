<?php

namespace App\Services\Documents\Security;

use App\Services\Documents\DTOs\FileSignatureValidationResult;

class FileSignatureValidator
{
    /**
     * Known magic signatures for permitted enterprise formats.
     */
    private const MAGIC_SIGNATURES = [
        'pdf'  => ["%PDF-"],
        'png'  => ["\x89PNG\r\n\x1a\n"],
        'jpeg' => ["\xFF\xD8\xFF"],
        'tiff' => ["II*\x00", "MM\x00*"],
        'zip'  => ["PK\x03\x04"], // DOCX, XLSX, PPTX
        'ole'  => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"], // Legacy DOC, XLS, PPT
    ];

    /**
     * Dangerous executable headers that must be strictly rejected.
     */
    private const BLOCKED_SIGNATURES = [
        "MZ",             // Windows PE executable / DLL
        "\x7FELF",         // Linux ELF executable
        "\xCA\xFE\xBA\xBE", // Java class / Mach-O universal binary
        "\xFE\xED\xFA\xCE", // Mach-O 32-bit
        "\xFE\xED\xFA\xCF", // Mach-O 64-bit
        "\xCF\xFA\xED\xFE", // Mach-O reverse endian
    ];

    public function validate(string $filePath, ?string $claimedExtension = null): FileSignatureValidationResult
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return FileSignatureValidationResult::invalid("Uploaded file cannot be read from storage.");
        }

        $size = filesize($filePath);
        if ($size === 0) {
            return FileSignatureValidationResult::invalid("Uploaded file is empty (0 bytes).");
        }

        // Read first 1024 bytes for signature detection
        $handle = fopen($filePath, 'rb');
        $header = fread($handle, min(1024, $size));
        fclose($handle);

        // 1. Strict executable detection check
        foreach (self::BLOCKED_SIGNATURES as $blocked) {
            if (str_starts_with($header, $blocked)) {
                return FileSignatureValidationResult::invalid("Security violation: Binary executable format detected and blocked.");
            }
        }

        // 2. Check for PDF
        if (str_starts_with($header, "%PDF-")) {
            return FileSignatureValidationResult::valid('pdf', 'application/pdf');
        }

        // 3. Check for PNG
        if (str_starts_with($header, "\x89PNG\r\n\x1a\n")) {
            return FileSignatureValidationResult::valid('png', 'image/png');
        }

        // 4. Check for JPEG
        if (str_starts_with($header, "\xFF\xD8\xFF")) {
            return FileSignatureValidationResult::valid('jpeg', 'image/jpeg');
        }

        // 5. Check for TIFF
        if (str_starts_with($header, "II*\x00") || str_starts_with($header, "MM\x00*")) {
            return FileSignatureValidationResult::valid('tiff', 'image/tiff');
        }

        // 6. Check for modern OOXML (DOCX, XLSX, PPTX) in ZIP container
        if (str_starts_with($header, "PK\x03\x04")) {
            $zipSubtype = $this->inspectZipArchiveSubtype($filePath);
            if ($zipSubtype) {
                return FileSignatureValidationResult::valid($zipSubtype['extension'], $zipSubtype['mime']);
            }
            return FileSignatureValidationResult::invalid("Unsupported or malformed ZIP package. Only DOCX, XLSX, and PPTX are permitted.");
        }

        // 7. Check for legacy OLE compound binary (DOC, XLS, PPT)
        if (str_starts_with($header, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            $oleSubtype = $this->inspectOleCompoundSubtype($filePath, $claimedExtension);
            return FileSignatureValidationResult::valid($oleSubtype['extension'], $oleSubtype['mime']);
        }

        // 8. Plain text and CSV checks
        if ($this->isValidTextOrCsv($header, $filePath)) {
            $isCsv = (strtolower((string) $claimedExtension) === 'csv') || str_contains($header, ',');
            return $isCsv
                ? FileSignatureValidationResult::valid('csv', 'text/csv')
                : FileSignatureValidationResult::valid('txt', 'text/plain');
        }

        return FileSignatureValidationResult::invalid("Unsupported file signature. Permitted formats: PDF, DOC, DOCX, TXT, XLS, XLSX, CSV, PPT, PPTX, JPEG, PNG, TIFF.");
    }

    private function inspectZipArchiveSubtype(string $filePath): ?array
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            return null;
        }

        $extension = null;
        $mime = null;

        if ($zip->locateName('word/document.xml') !== false) {
            $extension = 'docx';
            $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        } elseif ($zip->locateName('xl/workbook.xml') !== false || $zip->locateName('xl/worksheets/sheet1.xml') !== false) {
            $extension = 'xlsx';
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } elseif ($zip->locateName('ppt/presentation.xml') !== false || $zip->locateName('ppt/slides/slide1.xml') !== false) {
            $extension = 'pptx';
            $mime = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
        }

        $zip->close();

        return $extension ? ['extension' => $extension, 'mime' => $mime] : null;
    }

    private function inspectOleCompoundSubtype(string $filePath, ?string $claimed): array
    {
        $claimed = strtolower((string)$claimed);
        if (in_array($claimed, ['doc', 'xls', 'ppt'])) {
            $mimes = [
                'doc' => 'application/msword',
                'xls' => 'application/vnd.ms-excel',
                'ppt' => 'application/vnd.ms-powerpoint',
            ];
            return ['extension' => $claimed, 'mime' => $mimes[$claimed]];
        }

        return ['extension' => 'doc', 'mime' => 'application/msword'];
    }

    private function isValidTextOrCsv(string $sample, string $filePath): bool
    {
        // Must not contain binary null characters
        if (str_contains($sample, "\x00")) {
            return false;
        }

        // Must be valid UTF-8, ASCII or ISO-8859
        return mb_check_encoding($sample, 'UTF-8') || mb_check_encoding($sample, 'ISO-8859-1');
    }
}
