<?php

namespace App\Services\Comparison;

use App\Services\Comparison\DTOs\AnalyzedSectionDTO;

class DocumentStructureAnalyzer
{
    /**
     * Parse and structure raw chunk arrays into logical sections with clause numbers and categories.
     *
     * @param array<int, array<string, mixed>> $chunks
     * @return AnalyzedSectionDTO[]
     */
    public function analyzeStructure(array $chunks, string $slot): array
    {
        if (empty($chunks)) {
            return [];
        }

        $sections = [];
        $initialTitle = $this->detectInitialTitle($chunks[0]['content'] ?? '') ?: 'Document Overview';
        $currentTitle = $initialTitle;
        $currentClauseNum = $this->extractClauseNumber($initialTitle);
        $currentStartPage = 1;
        $currentEndPage = 1;
        $currentContent = [];
        $currentChunks = [];

        foreach ($chunks as $chunk) {
            $pageNumber = $chunk['page_number'] ?? 1;
            $title = $chunk['section_title'] ?? null;
            $content = $chunk['content'] ?? '';

            // Use chunk section title if available and distinct from current section title and generic placeholders
            if (! empty($title) && strcasecmp(trim($title), trim($currentTitle)) !== 0 && ! in_array(strtolower(trim($title)), ['preamble', 'document content', 'general provisions', 'general', 'document overview'])) {
                if (! empty($currentContent)) {
                    $sections[] = new AnalyzedSectionDTO(
                        id: "sec_{$slot}_" . (count($sections) + 1),
                        slot: $slot,
                        title: $currentTitle,
                        clauseNumber: $currentClauseNum,
                        pageStart: $currentStartPage,
                        pageEnd: $currentEndPage,
                        content: implode("\n\n", $currentContent),
                        category: $this->categorizeSection($currentTitle),
                        chunks: $currentChunks
                    );
                    $currentContent = [];
                    $currentChunks = [];
                }
                $currentTitle = $title;
                $currentClauseNum = $this->extractClauseNumber($title);
                $currentStartPage = $pageNumber;
                $currentEndPage = $pageNumber;
            }

            // Check if chunk contains internal headings separated by newlines
            $lines = explode("\n", $content);
            $buffer = [];

            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                $headingCandidate = $this->detectHeadingInText($trimmedLine);

                if ($headingCandidate && strcasecmp($headingCandidate, $currentTitle) !== 0) {
                    if (! empty($buffer)) {
                        $currentContent[] = implode("\n", $buffer);
                        $buffer = [];
                    }

                    if (! empty($currentContent)) {
                        $sections[] = new AnalyzedSectionDTO(
                            id: "sec_{$slot}_" . (count($sections) + 1),
                            slot: $slot,
                            title: $currentTitle,
                            clauseNumber: $currentClauseNum,
                            pageStart: $currentStartPage,
                            pageEnd: $currentEndPage,
                            content: implode("\n\n", $currentContent),
                            category: $this->categorizeSection($currentTitle),
                            chunks: $currentChunks
                        );
                        $currentContent = [];
                    }

                    $currentTitle = $headingCandidate;
                    $currentClauseNum = $this->extractClauseNumber($headingCandidate);
                    $currentStartPage = $pageNumber;
                    $currentEndPage = $pageNumber;

                    // If line had inline content after colon/heading, keep that content in buffer
                    if (str_contains($trimmedLine, ':')) {
                        $trailingContent = trim(substr($trimmedLine, strpos($trimmedLine, ':') + 1));
                        if (!empty($trailingContent)) {
                            $buffer[] = $trailingContent;
                        }
                    }
                } else {
                    $buffer[] = $line;
                }
            }

            if (! empty($buffer)) {
                $currentContent[] = implode("\n", $buffer);
            }
            $currentChunks[] = $chunk;
            $currentEndPage = max($currentEndPage, $pageNumber);
        }

        // Flush final section
        if (! empty($currentContent)) {
            $sections[] = new AnalyzedSectionDTO(
                id: "sec_{$slot}_" . (count($sections) + 1),
                slot: $slot,
                title: $currentTitle,
                clauseNumber: $currentClauseNum,
                pageStart: $currentStartPage,
                pageEnd: $currentEndPage,
                content: implode("\n\n", $currentContent),
                category: $this->categorizeSection($currentTitle),
                chunks: $currentChunks
            );
        }

        return $sections;
    }

    protected function detectInitialTitle(string $content): ?string
    {
        $lines = explode("\n", trim($content));
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }
            // If line is short and looks like a heading or document title
            if (strlen($trimmed) >= 3 && strlen($trimmed) <= 80 && ! preg_match('/[\.?!]$/', $trimmed)) {
                // Strip markdown leading hashes if present
                $clean = preg_replace('/^#+\s*/', '', $trimmed);
                return trim($clean);
            }
            // Only examine the first 3 non-empty lines
            break;
        }
        return null;
    }

    protected function detectHeadingInText(string $text): ?string
    {
        $firstLine = trim(strtok($text, "\n"));
        if (strlen($firstLine) < 3 || strlen($firstLine) > 100) {
            return null;
        }

        // 1. Markdown headings: # Heading, ## Heading
        if (preg_match('/^#{1,4}\s+(.+)$/', $firstLine, $matches)) {
            return trim($matches[1]);
        }

        // 2. Formal legal clauses: Article 1, Section 2.1, Clause 5, § 4
        if (preg_match('/^(?:Article|Section|Clause|\x{00A7})\s+([0-9A-Za-z\.\-]+)(?:[\.\s:\-–—]+(.*))?$/ui', $firstLine)) {
            if (str_contains($firstLine, ':')) {
                return trim(substr($firstLine, 0, strpos($firstLine, ':')));
            }
            return $firstLine;
        }

        // 3. Decimal numbered headings: 1.1 Definitions, 2.3 Payment Terms
        if (preg_match('/^\d+(?:\.\d+)+\s+[A-Z][A-Za-z0-9\s,\-_]+$/', $firstLine)) {
            return $firstLine;
        }

        // 4. Numbered list items with meaningful titles: 1. Product and vision lead, 2. Tech Lead
        if (preg_match('/^\d+\.\s+([A-Z][A-Za-z0-9\s\-_]{3,60})/i', $firstLine, $matches)) {
            return trim($matches[0]);
        }

        // 5. ALL CAPS section titles: ROLES, SCHEDULE, DELIVERABLES, SCOPE OF WORK
        if (preg_match('/^[A-Z0-9\s,\-_:]{3,50}$/', $firstLine) && preg_match('/[A-Z]{3,}/', $firstLine)) {
            $words = preg_split('/\s+/', trim($firstLine));
            if (count($words) <= 7) {
                return trim($firstLine);
            }
        }

        // 6. Section title ending with colon: "Roles for each member:" or "Schedule:"
        if (preg_match('/^([A-Z][A-Za-z0-9\s\-_]{2,50}):$/', $firstLine, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function extractClauseNumber(?string $text): ?string
    {
        if (! $text) {
            return null;
        }

        if (preg_match('/(?:Article|Section|Clause|\x{00A7})\s*([0-9]+(?:\.[0-9]+)*)/i', $text, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^(\d+(?:\.\d+)+)/', trim($text), $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function categorizeSection(string $title): string
    {
        $lower = strtolower($title);

        return match(true) {
            str_contains($lower, 'schedule') || str_contains($lower, 'routine') || str_contains($lower, 'wake up') || str_contains($lower, 'timing') || str_contains($lower, 'calendar') => 'Schedule & Time',
            str_contains($lower, 'role') || str_contains($lower, 'lead') || str_contains($lower, 'member') || str_contains($lower, 'assign') || str_contains($lower, 'duty') || str_contains($lower, 'responsib') => 'Roles & Assignments',
            str_contains($lower, 'process') || str_contains($lower, 'workflow') || str_contains($lower, 'thinking') || str_contains($lower, 'architecture') => 'Process & Workflow',
            str_contains($lower, 'task') || str_contains($lower, 'action') || str_contains($lower, 'milestone') || str_contains($lower, 'deliverable') => 'Tasks & Deliverables',
            str_contains($lower, 'indemn') || str_contains($lower, 'liabilit') || str_contains($lower, 'damage') => 'Indemnification & Liability',
            str_contains($lower, 'pay') || str_contains($lower, 'fee') || str_contains($lower, 'invoic') || str_contains($lower, 'price') || str_contains($lower, 'remunerat') => 'Payment Terms',
            str_contains($lower, 'terminat') || str_contains($lower, 'expir') || str_contains($lower, 'cancel') => 'Termination & Default',
            str_contains($lower, 'confidential') || str_contains($lower, 'non-disclosure') || str_contains($lower, 'nda') => 'Confidentiality',
            str_contains($lower, 'intellectual property') || str_contains($lower, 'ip') || str_contains($lower, 'patent') || str_contains($lower, 'copyright') || str_contains($lower, 'ownership') => 'Intellectual Property',
            str_contains($lower, 'govern') || str_contains($lower, 'jurisdiction') || str_contains($lower, 'arbitrat') || str_contains($lower, 'law') => 'Governing Law & Disputes',
            str_contains($lower, 'warrant') || str_contains($lower, 'guarante') || str_contains($lower, 'covenant') => 'Warranties & Representations',
            str_contains($lower, 'scope') || str_contains($lower, 'statement of work') || str_contains($lower, 'sow') => 'Scope of Work',
            default => 'General Provisions',
        };
    }
}
