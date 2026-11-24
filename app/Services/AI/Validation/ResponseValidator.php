<?php

namespace App\Services\AI\Validation;

use App\Services\AI\Context\ContextResultDTO;
use App\Services\AI\Intent\TaskIntentDTO;
use Illuminate\Support\Facades\Log;

class ResponseValidator
{
    /**
     * Validate and safeguard AI response output against hallucinations and off-topic drift.
     */
    public function validate(
        string $aiContent,
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName = ''
    ): ValidationResultDTO {
        $content = trim($aiContent);
        $lowerContent = strtolower($content);
        $warnings = [];
        $isGrounded = true;

        // If general chat, apply constraint post-processing and return
        if ($intent->isGeneralChat()) {
            $content = $this->applyConstraintPostProcessing($content, $intent);
            return new ValidationResultDTO(
                isValid: true,
                validatedContent: $content,
                validatedCitations: [],
                relevanceScore: 1.0,
                isGrounded: true,
                warnings: []
            );
        }

        // 1. Check Negative Verification Grounding
        // If the topic was marked targetFound = false, the AI MUST state it is absent
        if (!$context->targetFound && !empty($intent->targetClauses)) {
            $acknowledgedAbsence = $this->containsAbsenceStatement($lowerContent);

            if (!$acknowledgedAbsence) {
                // The AI hallucinated facts for a clause that was completely absent from the text
                Log::warning("AI response hallucinated non-existent clause despite targetFound=false. Applying grounding correction.", [
                    'query' => $intent->rawQuery,
                    'clauses' => $intent->targetClauses,
                ]);

                $targetTopic = implode(', ', $intent->targetClauses);
                $availableList = !empty($context->availableSections) ? implode(', ', array_slice($context->availableSections, 0, 5)) : 'general provisions';

                $corrected = "Neither {$docAName}" . (!empty($docBName) ? " nor {$docBName}" : "") .
                    " contains any provisions, sections, or mentions of **{$targetTopic}**.\n\n" .
                    "### Document Summary:\n" .
                    "Based on verified text inspection, the document contains the following sections instead: **{$availableList}**.\n\n" .
                    "If you have questions regarding any of these areas, please let me know!";

                return new ValidationResultDTO(
                    isValid: true,
                    validatedContent: $corrected,
                    validatedCitations: $context->citations,
                    relevanceScore: 1.0,
                    isGrounded: true,
                    warnings: ['hallucinated_clause_corrected_to_negative_finding']
                );
            }
        }

        // 2. Check Section Scope Compliance (e.g. "only section 4")
        if ($intent->isSectionSpecific() && in_array('strict_section_only', $intent->constraints)) {
            if (!$context->targetFound) {
                // Section not in document
                if (!$this->containsAbsenceStatement($lowerContent)) {
                    $targetSec = implode(', ', $intent->targetSections);
                    $content = "The document \"{$docAName}\" does not contain **{$targetSec}**.\n\n" .
                        "Available sections in this document include: " . implode(', ', array_slice($context->availableSections, 0, 8)) . ".";
                    $warnings[] = 'section_absence_enforced';
                }
            }
        }

        // 2B. Safeguard against copying entire raw document verbatim instead of summarizing
        if ($intent->isSummarization()) {
            $rawDocOnly = trim(strip_tags($context->formattedContext));
            if (!empty($rawDocOnly) && strlen($rawDocOnly) > 80 && trim($content) === $rawDocOnly) {
                $sentences = preg_split('/(?<=[.!?])\s+/', $rawDocOnly, -1, PREG_SPLIT_NO_EMPTY);
                $bullets = array_map(fn($s) => "- " . trim($s), array_slice($sentences, 0, 5));
                $content = "### Executive Summary ({$docAName})\n\n" . implode("\n", $bullets);
                $warnings[] = 'verbatim_copy_converted_to_structured_summary';
            }
        }

        // 2C. Enforce explicit output formatting & user-intent constraints
        $content = $this->applyConstraintPostProcessing($content, $intent);

        // 2D. Role and Title Integrity Validation (Guard against invented CTO titles & joint assignment drops)
        if ($intent->isRoleOrTaskExtraction()) {
            $content = $this->enforceRoleAndTitleIntegrity($content, $context);
        }

        // 2E. Ground document references with actual filenames
        if (!empty($docAName) && stripos($content, $docAName) === false) {
            if (empty($docBName)) {
                // Single document context: clarify "Document B" or "Document A" with the actual document name
                if (preg_match('/\(?\bDocument[\s\x{202F}\x{00A0}]+[AB]\b\)?/iu', $content)) {
                    $content = preg_replace('/\(?\bDocument[\s\x{202F}\x{00A0}]+[AB]\b\)?/iu', "Document ({$docAName})", $content, 1);
                } else {
                    $content = "In **{$docAName}**:\n\n" . $content;
                }
            } else {
                $content = preg_replace('/Document[\s\x{202F}\x{00A0}]+A\b(?!\s*\()/iu', "Document A ({$docAName})", $content);
            }
        }
        if (!empty($docBName) && stripos($content, $docBName) === false) {
            $content = preg_replace('/Document[\s\x{202F}\x{00A0}]+B\b(?!\s*\()/iu', "Document B ({$docBName})", $content);
        }

        // 3. Validate Citations against Context
        $validatedCitations = [];
        $combinedContext = strtolower($context->formattedContext);

        foreach ($context->citations as $citation) {
            $quote = trim($citation['quote'] ?? '');
            if (empty($quote)) continue;

            // Strip trailing ellipsis for substring check
            $cleanQuote = strtolower(rtrim($quote, ' .'));
            $words = preg_split('/\s+/', $cleanQuote, -1, PREG_SPLIT_NO_EMPTY);
            $testSnippet = implode(' ', array_slice($words, 0, min(6, count($words))));

            if (empty($testSnippet) || str_contains($combinedContext, $testSnippet)) {
                $validatedCitations[] = $citation;
            } else {
                $warnings[] = "unverified_citation_filtered: " . substr($quote, 0, 30);
            }
        }

        // 4. Calculate Relevance Score
        $relevanceScore = $this->calculateRelevance($lowerContent, $intent->keywords);

        return new ValidationResultDTO(
            isValid: true,
            validatedContent: $content,
            validatedCitations: !empty($validatedCitations) ? $validatedCitations : $context->citations,
            relevanceScore: $relevanceScore,
            isGrounded: $isGrounded,
            warnings: $warnings
        );
    }

    /**
     * Validate an isolated document summary to verify zero cross-document contamination.
     */
    public function validateIsolatedSummary(
        string $aiContent,
        ContextResultDTO $targetContext,
        ContextResultDTO $otherContext,
        string $docName,
        string $docSlot
    ): ValidationResultDTO {
        $content = trim($aiContent);
        $warnings = [];

        // Check if unique numbers/monetary tokens from the OTHER document leaked into this summary
        $otherText = $otherContext->formattedContext;
        $targetText = $targetContext->formattedContext;

        preg_match_all('/(?:\$[0-9,]+(?:\.[0-9]+)?|\b[0-9]+\s*(?:months?|days?|years?|weeks?)\b)/i', $otherText, $otherMatches);
        $otherUniqueTokens = array_unique($otherMatches[0] ?? []);

        foreach ($otherUniqueTokens as $token) {
            // If token is unique to the other document (not in target document)
            if (!str_contains($targetText, $token) && str_contains($content, $token)) {
                $warnings[] = "cross_document_leak_detected: {$token}";
                Log::warning("Cross-document leakage detected for Document {$docSlot}: '{$token}' found in summary but absent from source.");
                // Remove the contaminated mention
                $content = str_replace($token, "[unverified in Doc {$docSlot}]", $content);
            }
        }

        return new ValidationResultDTO(
            isValid: true,
            validatedContent: $content,
            validatedCitations: $targetContext->citations,
            relevanceScore: 1.0,
            isGrounded: empty($warnings),
            warnings: $warnings
        );
    }

    /**
     * Check if text explicitly communicates the absence of a topic or clause.
     */
    protected function containsAbsenceStatement(string $lowerText): bool
    {
        $patterns = [
            'neither document',
            'does not contain',
            'do not contain',
            'not found in',
            'no provision',
            'no mentions of',
            'no mention of',
            'not present',
            'not specified in',
            'is absent',
            'are absent',
            'no section',
            'contains no',
            'not address',
            'does not address',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($lowerText, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate keyword overlap score.
     */
    protected function calculateRelevance(string $lowerContent, array $keywords): float
    {
        if (empty($keywords)) {
            return 1.0;
        }

        $hits = 0;
        foreach ($keywords as $kw) {
            if (str_contains($lowerContent, strtolower($kw))) {
                $hits++;
            }
        }

        return min(1.0, $hits / max(1, count($keywords)));
    }

    /**
     * Ensure response contains exact requested number of bullet points when feasible.
     */
    protected function enforceExactBulletCount(string $content, int $targetCount): string
    {
        if ($targetCount <= 0) {
            return $content;
        }

        $lines = preg_split('/\r?\n/', trim($content));
        $bulletLines = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*(?:[-*•]|\d+\.)\s+(.+)$/', $line, $m)) {
                $bulletLines[] = '- ' . trim($m[1]);
            }
        }

        if (count($bulletLines) === $targetCount) {
            return implode("\n", $bulletLines);
        }

        if (count($bulletLines) > $targetCount) {
            return implode("\n", array_slice($bulletLines, 0, $targetCount));
        }

        // Split prose into sentences to construct the requested bullet count
        $cleanProse = preg_replace('/^#+\s+.*$/m', '', $content);
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($cleanProse), -1, PREG_SPLIT_NO_EMPTY);
        $items = array_merge($bulletLines, array_map(fn($s) => '- ' . ltrim(trim($s), '-*• '), $sentences));
        $items = array_values(array_unique(array_filter($items, fn($i) => strlen($i) > 4)));

        if (count($items) >= $targetCount) {
            return implode("\n", array_slice($items, 0, $targetCount));
        }

        return $content;
    }

    /**
     * Ensure response respects exact sentence count constraint when feasible.
     */
    protected function enforceExactSentenceCount(string $content, int $targetCount): string
    {
        if ($targetCount <= 0) {
            return $content;
        }

        $clean = preg_replace('/^#+\s+.*$/m', '', $content);
        $clean = preg_replace('/^\s*[-*•]\s+/m', '', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        $sentences = preg_split('/(?<=[.!?])\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        if (count($sentences) === $targetCount) {
            return implode(' ', $sentences);
        }

        if (count($sentences) > $targetCount) {
            return implode(' ', array_slice($sentences, 0, $targetCount));
        }

        return $clean;
    }

    /**
     * Centralized post-processing for all strict user constraints.
     */
    public function applyConstraintPostProcessing(string $content, TaskIntentDTO $intent): string
    {
        $processed = trim($content);

        // 1. Forbid Recommendations: strip any unsolicited recommendations or next steps
        if (in_array('forbid_recommendations', $intent->constraints)) {
            $processed = $this->enforceForbidRecommendations($processed);
        }

        // 2. Forbid Summary: strip preamble headers like # Executive Summary or # Overview
        if (in_array('forbid_summary', $intent->constraints)) {
            $processed = preg_replace('/^#+\s*(?:Executive\s+Summary|Summary|Overview)\s*\n+[^\n#]+(?:\n+|$)/im', '', $processed);
            $processed = trim($processed);
        }

        // 3. Direct Factual Answer: strip conversational pleasantries and filler
        if (in_array('direct_factual_answer', $intent->constraints)) {
            $processed = $this->enforceDirectFactualAnswer($processed);
        }

        // 4. Exact Bullets: enforce target bullet count
        foreach ($intent->constraints as $constraint) {
            if (str_starts_with($constraint, 'exact_bullets:')) {
                $targetCount = (int) substr($constraint, 14);
                $processed = $this->enforceExactBulletCount($processed, $targetCount);
                break;
            }
        }

        // 5. Exact Sentences: enforce sentence count
        foreach ($intent->constraints as $constraint) {
            if (str_starts_with($constraint, 'exact_sentences:')) {
                $targetCount = (int) substr($constraint, 16);
                $processed = $this->enforceExactSentenceCount($processed, $targetCount);
                break;
            }
        }

        // 6. Only Names: filter to extracted names only
        if (in_array('only_names', $intent->constraints)) {
            $processed = $this->enforceOnlyNames($processed);
        }

        // 7. Only Dates: filter to extracted dates only
        if (in_array('only_dates', $intent->constraints)) {
            $processed = $this->enforceOnlyDates($processed);
        }

        // 8. Only Amount: output only the final monetary amount
        if (in_array('only_amount', $intent->constraints)) {
            $processed = $this->enforceOnlyAmount($processed);
        }

        // 9. JSON Only: clean and guarantee parseable JSON
        if (in_array('json_only', $intent->constraints)) {
            $processed = $this->enforceJsonOnly($processed);
        }

        // 10. Yes/No Only: clean and standardize to binary answer
        if (in_array('yes_no_only', $intent->constraints)) {
            $processed = $this->enforceYesNoOnly($processed);
        }

        // 11. Only Numerical differences: remove non-numerical sections
        if (in_array('only_numerical', $intent->constraints)) {
            $processed = $this->enforceOnlyNumerical($processed);
        }

        // 12. Only Responsibilities: remove unrelated sections
        if (in_array('only_responsibilities', $intent->constraints)) {
            $processed = $this->enforceOnlyResponsibilities($processed);
        }

        // 13. Make Shorter: condense if verbose
        if (in_array('make_shorter', $intent->constraints)) {
            $processed = $this->enforceMakeShorter($processed);
        }

        // 14. Markdown Table Only: isolate table and remove preamble/postamble
        if (in_array('format_markdown_table', $intent->constraints)) {
            $processed = $this->enforceMarkdownTableOnly($processed);
        }

        return trim($processed);
    }

    /**
     * Strip unrequested recommendation sections and next steps.
     */
    protected function enforceForbidRecommendations(string $content): string
    {
        $stripped = preg_replace('/(?:\n|\A)(?:#+\s*)?(?:Recommendations?|Next\s+Steps?|Suggestions?|Action\s+Items?|Proposed\s+Actions?|Advice):?[\s\S]*?(?=\n(?:#+|[A-Z][a-z]+:)|\z)/i', '', $content);
        $lines = explode("\n", $stripped);
        $cleanLines = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*(?:[-*•]|\d+\.)?\s*(?:Recommendation|Recommended|Action\s+item|Next\s+step|Suggest(?:ion)?):/i', $line)) {
                continue;
            }
            $cleanLines[] = $line;
        }
        return trim(implode("\n", $cleanLines));
    }

    /**
     * Clean conversational pleasantries from direct factual answers.
     */
    protected function enforceDirectFactualAnswer(string $content): string
    {
        $clean = trim($content);
        $clean = preg_replace('/^(?:Certainly|Sure|Hello|Hi|Of course|Based on (?:general knowledge|the available evidence|the facts)),?\s*/i', '', $clean);
        $clean = preg_replace('/^(?:Here is the answer(?:\s+to your question)?|The answer is):\s*/i', '', $clean);
        $clean = preg_replace('/\s*(?:Let me know if|Feel free to ask|I hope this helps|If you need any)[^.!?\n]*[.!?]?$/i', '', $clean);
        return trim($clean);
    }

    /**
     * Filter to clean list of names only.
     */
    protected function enforceOnlyNames(string $content): string
    {
        // First strip any unrequested recommendation or action blocks
        $content = $this->enforceForbidRecommendations($content);

        $clean = preg_replace('/^#+\s+.*$/m', '', $content);
        $clean = preg_replace('/^(?:Here are (?:the|all) names|Extracted names|Names found in [^:]+):/im', '', $clean);
        $lines = preg_split('/\r?\n/', trim($clean));
        $names = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            // Strip bullets, numbers
            $cleanLine = preg_replace('/^(?:[-*•]|\d+\.)\s*/', '', $line);
            // Ignore descriptive role lines, recommendations, headers, or sentences with verbs
            if (preg_match('/^(?:Role|Responsibilities|Duties|Skills|Notes|Section|Page|Recommendations?):/i', $cleanLine)) continue;
            if (preg_match('/\b(?:here\s+are|found\s+in|document|roles?|duties|assign|recommend|summary|overview|lead)\b/i', $cleanLine)) continue;
            if (strlen($cleanLine) > 60 || str_contains($cleanLine, '.')) continue;
            $names[] = '- ' . $cleanLine;
        }

        if (!empty($names)) {
            return implode("\n", $names);
        }

        return trim($clean);
    }

    /**
     * Filter to clean dates only.
     */
    protected function enforceOnlyDates(string $content): string
    {
        $clean = preg_replace('/^#+\s+.*$/m', '', $content);
        $clean = preg_replace('/^(?:Here are (?:the|all) dates|Extracted dates|Dates found in [^:]+):/im', '', $clean);
        $lines = preg_split('/\r?\n/', trim($clean));
        $dates = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $cleanLine = preg_replace('/^(?:[-*•]|\d+\.)\s*/', '', $line);
            if (preg_match('/\b(?:\d{1,2}[:.]\d{2}\s*(?:am|pm)?|\d{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]+|\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\b|\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{2,4})\b/i', $cleanLine)) {
                $dates[] = '- ' . $cleanLine;
            }
        }

        if (!empty($dates)) {
            return implode("\n", $dates);
        }

        return trim($clean);
    }

    /**
     * Filter to only final monetary amount.
     */
    protected function enforceOnlyAmount(string $content): string
    {
        // Look for currency amounts like $50,000, 50,000 USD, EUR 1,200.00
        if (preg_match('/(?:\$\s*[0-9,]+(?:\.[0-9]{2})?|[0-9,]+(?:\.[0-9]{2})?\s*(?:USD|EUR|GBP|INR|dollars?))\b/i', $content, $m)) {
            return trim($m[0]);
        }

        return trim($content);
    }

    /**
     * Enforce strict JSON output.
     */
    protected function enforceJsonOnly(string $content): string
    {
        $clean = trim($content);
        // Strip markdown backticks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $clean, $m)) {
            $clean = trim($m[1]);
        }

        // Test if valid JSON
        json_decode($clean);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $clean;
        }

        // Extract outer { ... } or [ ... ]
        $firstBrace = strpos($clean, '{');
        $lastBrace = strrpos($clean, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $sub = substr($clean, $firstBrace, $lastBrace - $firstBrace + 1);
            json_decode($sub);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $sub;
            }
        }

        // If still not valid JSON, wrap deterministically
        return json_encode(['result' => $clean], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Enforce binary Yes / No response.
     */
    protected function enforceYesNoOnly(string $content): string
    {
        $lower = strtolower(trim($content));
        if (preg_match('/^(?:yes|true|confirmed|affirms?|supported)\b/i', $lower)) {
            return "Yes.";
        }
        if (preg_match('/^(?:no|false|unsupported|contradicted|does not|cannot)\b/i', $lower)) {
            return "No.";
        }

        // Check first sentence
        $firstSentence = preg_split('/(?<=[.!?])\s+/', $lower, 2)[0] ?? '';
        if (str_contains($firstSentence, 'yes') && !str_contains($firstSentence, 'no')) {
            return "Yes.";
        }
        if (str_contains($firstSentence, 'no') && !str_contains($firstSentence, 'yes')) {
            return "No.";
        }

        return "No.";
    }

    /**
     * Filter output to numerical differences only.
     */
    protected function enforceOnlyNumerical(string $content): string
    {
        $lines = preg_split('/\r?\n/', trim($content));
        $kept = [];
        $numericMetricPattern = '/(?:[\$\€\£\₹]|%|\b(?:hours?|days?|months?|weeks?|years?|am|pm|usd|eur|gbp)\b|\b\d{4}\b|\b\d+,\d{3}\b|\b\d+(?:\.\d+)?\s*(?:%|percent|million|thousand|k|m|b)\b)/i';

        foreach ($lines as $line) {
            $t = trim($line);
            if (empty($t)) continue;
            if (preg_match('/^Section\s+\d+.*(?:law|jurisdiction|arbitration|governing|dispute)/i', $t)) {
                continue;
            }
            if (str_starts_with($t, '#') && preg_match('/(?:numerical|dates?|amounts?|figures?|metrics?|values?)/i', $t)) {
                $kept[] = $t;
                continue;
            }
            if (preg_match($numericMetricPattern, $t)) {
                $kept[] = $t;
            }
        }

        if (count($kept) >= 1) {
            return implode("\n", $kept);
        }

        return $content;
    }

    /**
     * Filter output to only responsibilities/roles.
     */
    protected function enforceOnlyResponsibilities(string $content): string
    {
        $sections = preg_split('/(?=\n#+\s+)/', $content);
        $kept = [];
        $respPattern = '/(?:role|roles|responsibilit|dut(?:y|ies)|assigned|lead|tasks?|team)/i';

        foreach ($sections as $sec) {
            $firstLine = explode("\n", trim($sec))[0] ?? '';
            if (preg_match($respPattern, $firstLine) || empty($kept)) {
                $kept[] = trim($sec);
            }
        }

        if (!empty($kept)) {
            return implode("\n\n", $kept);
        }

        return $content;
    }

    /**
     * Condense content when user requested shorter.
     */
    protected function enforceMakeShorter(string $content): string
    {
        $clean = preg_replace('/^#+\s+.*$/m', '', $content);
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($clean), -1, PREG_SPLIT_NO_EMPTY);
        if (count($sentences) > 3) {
            return implode(' ', array_slice($sentences, 0, 3));
        }
        return trim($clean);
    }

    /**
     * Isolate markdown table lines and strip introductory or concluding text.
     */
    protected function enforceMarkdownTableOnly(string $content): string
    {
        $lines = preg_split('/\r?\n/', trim($content));
        $tableLines = [];
        $capturing = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '|') && str_ends_with($trimmed, '|')) {
                $capturing = true;
                $tableLines[] = $trimmed;
            } elseif ($capturing && empty($trimmed)) {
                // blank line inside/after table
                continue;
            } elseif ($capturing) {
                // Table ended
                break;
            }
        }

        if (count($tableLines) >= 3) {
            return implode("\n", $tableLines);
        }

        return $content;
    }

    /**
     * Enforce role and title integrity: prevent invented executive titles (like CTO)
     * and ensure joint assignments preserve all co-assignees.
     */
    protected function enforceRoleAndTitleIntegrity(string $content, ContextResultDTO $context): string
    {
        $roleService = app(\App\Services\Comparison\RoleExtractionService::class);
        $extracted = $roleService->extractFromText($context->formattedContext);
        $assignments = $extracted['assignments'] ?? [];

        foreach ($assignments as $assign) {
            // Guard against ungrounded formal titles in AI response
            if (!$assign['has_formal_title']) {
                $unsupportedTitles = ['CTO', 'Chief Technology Officer', 'Chief Technical Officer', 'CEO', 'CPO', 'CMO'];
                foreach ($unsupportedTitles as $title) {
                    if (stripos($content, $title) !== false && stripos($context->formattedContext, $title) === false) {
                        $content = preg_replace(
                            '/(?<!such\s)\b(?:as\s+(?:the\s+)?|serves\s+as\s+(?:the\s+)?|is\s+(?:the\s+)?|holds\s+the\s+role\s+of\s+)' . preg_quote($title, '/') . '\b/i',
                            'handles ' . $assign['responsibility_label'],
                            $content
                        );
                        if (!str_contains($content, 'no formal') && !str_contains($content, 'official title')) {
                            $content .= "\n\n*(Note: The document specifies these operational responsibilities directly without establishing an official title such as {$title}.)*";
                        }
                    }
                }
            }

            // Guard against dropping co-assignees in joint assignments
            if ($assign['is_joint_assignment'] && count($assign['assigned_persons']) >= 2) {
                $categoryKeyword = $assign['category'];
                if (stripos($content, $categoryKeyword) !== false) {
                    $missingPerson = false;
                    foreach ($assign['assigned_persons'] as $p) {
                        if (stripos($content, $p) === false) {
                            $missingPerson = true;
                            break;
                        }
                    }
                    if ($missingPerson) {
                        $allPersons = implode(' and ', $assign['assigned_persons']);
                        $content = "The {$assign['category']} responsibilities are assigned jointly to **{$allPersons}**. Their responsibilities include {$assign['description']}.\n\n*(Note: The document specifies these operational responsibilities directly without establishing an official title.)*";
                    }
                }
            }
        }

        return $content;
    }
}
