<?php

namespace App\Services\Documents\OCR;

use App\Services\Documents\DTOs\ExtractedPageDTO;

class ScannedPageDetector
{
    /**
     * Minimum word count threshold below which a non-empty page
     * is suspected of being a scanned image document page.
     */
    public const WORD_COUNT_THRESHOLD = 15;

    /**
     * Detect if a page is a scanned document (image-heavy or lack of text stream).
     */
    public function isScanned(ExtractedPageDTO $page): bool
    {
        // If already marked as scanned (e.g. from an image parser)
        if ($page->isScanned) {
            return true;
        }

        $trimmed = trim($page->text);

        // Blank page
        if (empty($trimmed)) {
            return false;
        }

        // If word count is below threshold, it's very likely a rasterized scan or emblem
        if ($page->wordCount > 0 && $page->wordCount < self::WORD_COUNT_THRESHOLD) {
            return true;
        }

        return false;
    }

    /**
     * Inspect all pages of a document and flag scanned pages.
     *
     * @param ExtractedPageDTO[] $pages
     * @return ExtractedPageDTO[]
     */
    public function detectAndFlagPages(array $pages): array
    {
        $updated = [];
        foreach ($pages as $page) {
            $isScanned = $this->isScanned($page);
            $updated[] = new ExtractedPageDTO(
                pageNumber: $page->pageNumber,
                text: $page->text,
                tables: $page->tables,
                sections: $page->sections,
                isScanned: $isScanned,
                wordCount: $page->wordCount
            );
        }
        return $updated;
    }
}
