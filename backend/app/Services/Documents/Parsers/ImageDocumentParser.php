<?php

namespace App\Services\Documents\Parsers;

use App\Services\Documents\Contracts\DocumentParserInterface;
use App\Services\Documents\Contracts\OCRServiceInterface;
use App\Services\Documents\DTOs\ExtractedDocumentDTO;
use App\Services\Documents\DTOs\ExtractedPageDTO;

class ImageDocumentParser implements DocumentParserInterface
{
    public function __construct(
        protected OCRServiceInterface $ocrService
    ) {}

    public function supports(string $extension): bool
    {
        return in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'tiff', 'tif']);
    }

    public function parse(string $filePath, array $options = []): ExtractedDocumentDTO
    {
        $imageInfo = @getimagesize($filePath);
        if (! $imageInfo) {
            throw new \RuntimeException("Corrupt image data or unreadable image dimensions.");
        }

        // Perform OCR extraction on image
        $page = $this->ocrService->extractTextFromImage($filePath, 1);

        return new ExtractedDocumentDTO(
            originalName: basename($filePath),
            format: image_type_to_extension($imageInfo[2], false) ?: 'jpeg',
            totalPages: 1,
            pages: [$page],
            metadata: [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'mime' => $imageInfo['mime'],
            ],
            totalWordCount: $page->wordCount
        );
    }
}
