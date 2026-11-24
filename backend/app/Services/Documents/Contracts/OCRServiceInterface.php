<?php

namespace App\Services\Documents\Contracts;

use App\Services\Documents\DTOs\ExtractedPageDTO;

interface OCRServiceInterface
{
    /**
     * Perform OCR extraction on image or rasterized scanned page.
     */
    public function extractTextFromImage(string $imagePath, int $pageNumber = 1): ExtractedPageDTO;

    /**
     * Determine if an OCR engine binary (e.g. tesseract) is available.
     */
    public function isAvailable(): bool;
}
