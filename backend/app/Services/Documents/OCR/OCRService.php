<?php

namespace App\Services\Documents\OCR;

use App\Services\Documents\Contracts\OCRServiceInterface;
use App\Services\Documents\DTOs\ExtractedPageDTO;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class OCRService implements OCRServiceInterface
{
    protected ?bool $tesseractAvailable = null;

    /**
     * Determine if tesseract OCR engine binary is available in system PATH.
     */
    public function isAvailable(): bool
    {
        if ($this->tesseractAvailable !== null) {
            return $this->tesseractAvailable;
        }

        try {
            $process = new Process(['which', 'tesseract']);
            $process->setTimeout(2);
            $process->run();
            $this->tesseractAvailable = $process->isSuccessful() && ! empty(trim($process->getOutput()));
        } catch (\Throwable $e) {
            $this->tesseractAvailable = false;
        }

        return $this->tesseractAvailable;
    }

    /**
     * Perform OCR extraction on image or rasterized scanned page.
     */
    public function extractTextFromImage(string $imagePath, int $pageNumber = 1): ExtractedPageDTO
    {
        if (! file_exists($imagePath)) {
            return new ExtractedPageDTO(
                pageNumber: $pageNumber,
                text: '',
                tables: [],
                sections: [],
                isScanned: true,
                wordCount: 0
            );
        }

        if ($this->isAvailable()) {
            try {
                $process = new Process([
                    'tesseract',
                    $imagePath,
                    'stdout',
                    '--oem', '1',
                    '-l', 'eng',
                ]);
                $process->setTimeout(60);
                $process->run();

                if ($process->isSuccessful()) {
                    $text = trim($process->getOutput());
                    $wordCount = str_word_count($text);

                    return new ExtractedPageDTO(
                        pageNumber: $pageNumber,
                        text: $text,
                        tables: [],
                        sections: [],
                        isScanned: true,
                        wordCount: $wordCount
                    );
                }

                Log::warning('OCR execution non-zero exit', ['output' => $process->getErrorOutput()]);
            } catch (\Throwable $e) {
                Log::error('OCR execution failed: ' . $e->getMessage());
            }
        }

        // Safe Fallback when Tesseract is not installed on system:
        // Inspect image properties so pipeline proceeds deterministically
        $imageInfo = @getimagesize($imagePath);
        $width = $imageInfo[0] ?? 0;
        $height = $imageInfo[1] ?? 0;
        $mime = $imageInfo['mime'] ?? 'image/unknown';

        $fallbackText = "[Scanned Image Page {$pageNumber} - Dimensions: {$width}x{$height}, Type: {$mime}]";

        return new ExtractedPageDTO(
            pageNumber: $pageNumber,
            text: $fallbackText,
            tables: [],
            sections: [],
            isScanned: true,
            wordCount: str_word_count($fallbackText)
        );
    }
}
