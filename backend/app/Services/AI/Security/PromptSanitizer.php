<?php

namespace App\Services\AI\Security;

class PromptSanitizer
{
    /**
     * Dangerous directive strings that attempts prompt injection.
     */
    protected const INJECTION_PATTERNS = [
        '/\bignore\s+(all\s+)?(previous|prior|above)\s+instructions\b/i',
        '/\byou\s+are\s+now\s+(an?\s+)?unrestricted\b/i',
        '/\bsystem\s*:\s*/i',
        '/\b<\|im_start\|>/i',
        '/\b<\|im_end\|>/i',
        '/\b\[INST\]/i',
        '/\b\[\/INST\]/i',
    ];

    /**
     * Sanitize user input to mitigate prompt injection.
     */
    public function sanitize(string $text): string
    {
        // 1. Remove non-printable control chars except \n and \t
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        // 2. Neutralize injection directive patterns
        foreach (self::INJECTION_PATTERNS as $pattern) {
            $text = preg_replace($pattern, '[BLOCKED_DIRECTIVE]', $text);
        }

        return trim($text);
    }

    /**
     * Wrap document excerpt into safe, isolated XML boundaries for the model.
     */
    public function wrapDocumentContext(string $slot, int $pageNumber, string $content, ?string $sectionTitle = null, ?string $documentName = null): string
    {
        $sanitizedContent = $this->sanitize($content);
        $escapedTitle = $sectionTitle ? htmlspecialchars($sectionTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'None';
        $escapedDoc = $documentName ? htmlspecialchars($documentName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : "Document {$slot}";

        return <<<XML
<document_content slot="{$slot}" document="{$escapedDoc}" page="{$pageNumber}" section="{$escapedTitle}">
{$sanitizedContent}
</document_content>
XML;
    }

    /**
     * Sanitize generated model output.
     */
    public function sanitizeOutput(string $output): string
    {
        // Strip markdown backtick wrappers if model wrapped json in ```json ... ```
        if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/i', trim($output), $matches)) {
            return trim($matches[1]);
        }

        return trim($output);
    }
}
