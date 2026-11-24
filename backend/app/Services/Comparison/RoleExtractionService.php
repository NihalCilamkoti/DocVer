<?php

namespace App\Services\Comparison;

use App\Services\Comparison\DTOs\AnalyzedSectionDTO;

class RoleExtractionService
{
    /**
     * Formal executive titles that may appear in organizational documents.
     */
    protected const FORMAL_TITLES = [
        'cto', 'chief technology officer', 'chief technical officer',
        'ceo', 'chief executive officer',
        'cpo', 'chief product officer',
        'cmo', 'chief marketing officer',
        'coo', 'chief operating officer',
        'cfo', 'chief financial officer',
        'principal architect', 'ai architect',
        'engineering lead', 'technical lead', 'tech lead',
        'product lead', 'growth lead', 'marketing lead', 'operations lead',
        'vice president', 'director', 'manager', 'head of engineering',
        'head of product', 'head of technology',
    ];

    /**
     * Functional category keyword mappings.
     */
    protected const CATEGORY_KEYWORDS = [
        'technical' => ['technology', 'technical', 'tech', 'camera', 'web application', 'web app', 'blockchain', 'backend', 'frontend', 'software', 'engineering', 'code', 'coding', 'architecture', 'infrastructure', 'devops', 'database'],
        'product' => ['product', 'product vision', 'roadmap', 'features', 'ui/ux', 'design', 'user experience', 'prototype'],
        'marketing' => ['marketing', 'growth', 'user acquisition', 'sales', 'social media', 'outreach', 'branding', 'campaigns'],
        'management' => ['management', 'leadership', 'executive', 'director', 'oversight', 'governance', 'coordination', 'founder'],
        'operations' => ['operations', 'operational', 'logistics', 'cadence', 'sprint', 'standup', 'schedule', 'planning'],
        'financial' => ['finance', 'financial', 'budget', 'accounting', 'treasury', 'revenue', 'pricing'],
        'qa' => ['qa', 'quality assurance', 'testing', 'compliance', 'security', 'audit'],
    ];

    /**
     * Extract structured role assignments from document chunks or text.
     *
     * @param array $chunks
     * @param string $slot
     * @param string $docName
     * @return array{assignments: array, distinct_members: array, explicit_member_count: ?int}
     */
    public function extractRolesFromChunks(array $chunks, string $slot = 'B', string $docName = 'Document B'): array
    {
        $assignments = [];
        $distinctMembers = [];
        $explicitCount = null;

        foreach ($chunks as $chunk) {
            $content = $chunk['content'] ?? '';
            $page = (int) ($chunk['page_number'] ?? 1);

            $extracted = $this->extractFromText($content, $slot, $docName, $page);
            foreach ($extracted['assignments'] as $assign) {
                $assignments[] = $assign;
                foreach ($assign['assigned_persons'] as $person) {
                    $norm = trim($person);
                    if (!empty($norm) && !in_array($norm, $distinctMembers)) {
                        $distinctMembers[] = $norm;
                    }
                }
            }

            if ($explicitCount === null && $extracted['explicit_member_count'] !== null) {
                $explicitCount = $extracted['explicit_member_count'];
            }
        }

        // Deduplicate assignments across chunks by person + category
        $dedupedAssignments = [];
        foreach ($assignments as $a) {
            $key = implode('_', $a['assigned_persons']) . '_' . $a['category'];
            if (!isset($dedupedAssignments[$key])) {
                $dedupedAssignments[$key] = $a;
            } else {
                // If existing doesn't have detailed description, update
                if (strlen($a['description']) > strlen($dedupedAssignments[$key]['description'])) {
                    $dedupedAssignments[$key] = $a;
                }
            }
        }

        $finalAssignments = array_values($dedupedAssignments);

        return [
            'assignments' => $finalAssignments,
            'distinct_members' => array_values($distinctMembers),
            'explicit_member_count' => $explicitCount ?? count($distinctMembers),
        ];
    }

    /**
     * Extract role assignments from raw text.
     */
    public function extractFromText(string $text, string $slot = 'B', string $docName = 'Document B', int $page = 1): array
    {
        $assignments = [];
        $distinctMembers = [];
        $explicitCount = null;

        // Check for explicit member count statements
        if (preg_match('/\b(?:total\s+(?:core\s+)?team\s+size|team\s+of|total\s+members?|active\s+members?):\s*(?:(?:five|four|three|six|seven|eight|two|\d+)\s*)?\(?(\d+)\)?/i', $text, $countMatch)) {
            $explicitCount = (int) $countMatch[1];
        } elseif (preg_match('/\b(?:total\s+(?:core\s+)?team\s+size|team\s+of|total\s+members?|active\s+members?):\s*(five|four|three|six|seven|eight|two|\d+)\b/i', $text, $wordMatch)) {
            $numMap = ['two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8];
            $val = strtolower($wordMatch[1]);
            $explicitCount = is_numeric($val) ? (int)$val : ($numMap[$val] ?? null);
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        $currentPerson = null;
        $currentRole = null;
        $currentDesc = [];
        $currentQuote = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) continue;

            // Pattern 1: Bullet with Person Name and Role / Responsibility
            // e.g., "- Nihal Cilamkoti: Product Lead and Principal AI Architect."
            // e.g., "- Gideon Raj and Nihal: Technology behind the camera, web application, and blockchain"
            // e.g., "- Technology: Taken by Gideon Raj and Nihal"
            // e.g., "1. Technical Role: Gideon Raj and Nihal (responsible for camera, web app)"
            if (preg_match('/^(?:[-*•]|\d+\.)\s*(.*?)(?::|\s*-\s*|\s*–\s*)(.*)$/u', $trimmed, $m)) {
                $left = trim($m[1]);
                $right = trim($m[2]);

                $parsed = $this->parseAssignmentPair($left, $right, $trimmed, $slot, $docName, $page);
                if ($parsed !== null) {
                    $assignments[] = $parsed;
                    foreach ($parsed['assigned_persons'] as $p) {
                        if (!in_array($p, $distinctMembers)) {
                            $distinctMembers[] = $p;
                        }
                    }
                    continue;
                }
            }

            // Pattern 2: "Technology taken by Gideon Raj and Nihal"
            if (preg_match('/^(.*?)\s+(?:is\s+)?(?:taken\s+by|assigned\s+(?:jointly\s+)?to|handled\s+by|led\s+by)\s+(.*?)$/i', $trimmed, $m)) {
                $duty = trim($m[1]);
                $persons = trim($m[2]);

                $parsed = $this->parseAssignmentPair($persons, $duty, $trimmed, $slot, $docName, $page, true);
                if ($parsed !== null) {
                    $assignments[] = $parsed;
                    foreach ($parsed['assigned_persons'] as $p) {
                        if (!in_array($p, $distinctMembers)) {
                            $distinctMembers[] = $p;
                        }
                    }
                    continue;
                }
            }

            // Sub-bullets under an existing person (e.g. "* Primary Responsibilities: Overall product vision...")
            if (!empty($assignments) && preg_match('/^(?:[*•]|\s{2,}-)\s*(?:primary\s+responsibilities|responsibilities|deliverables|duties|focus):\s*(.*)$/i', $trimmed, $subMatch)) {
                $lastIdx = count($assignments) - 1;
                $assignments[$lastIdx]['description'] .= ' ' . trim($subMatch[1]);
                $assignments[$lastIdx]['supporting_text'] .= "\n" . $trimmed;
            }
        }

        return [
            'assignments' => $assignments,
            'distinct_members' => array_values($distinctMembers),
            'explicit_member_count' => $explicitCount,
        ];
    }

    /**
     * Parse left and right parts of an assignment line.
     */
    protected function parseAssignmentPair(
        string $partA,
        string $partB,
        string $fullLine,
        string $slot,
        string $docName,
        int $page,
        bool $reversed = false
    ): ?array {
        $personPart = $reversed ? $partA : $partA;
        $rolePart = $reversed ? $partB : $partB;

        // Check if partA looks like a role/category rather than person names
        // e.g. "Technology", "Technical Role", "Product", "Operations"
        $categoryA = $this->detectCategory($partA);
        $categoryB = $this->detectCategory($partB);

        if ($categoryA !== 'general' && preg_match('/\b(?:taken\s+by|assigned\s+to|handled\s+by|by)\s+(.*)/i', $partB, $byMatch)) {
            $personPart = $byMatch[1];
            $rolePart = $partA . ' - ' . preg_replace('/\b(?:taken\s+by|assigned\s+to|handled\s+by|by)\s+/i', '', $partB);
        } elseif ($categoryA !== 'general' && $this->looksLikePersonName($partB)) {
            $personPart = $partB;
            $rolePart = $partA;
        }

        $persons = $this->extractPersonNames($personPart);
        if (empty($persons)) {
            return null;
        }

        // Determine if there is a formal job title vs purely a responsibility description
        $formalTitle = $this->detectFormalTitle($rolePart);
        $hasFormalTitle = !empty($formalTitle);

        // Detect category
        $category = $this->detectCategory($rolePart . ' ' . $fullLine);

        $isJoint = count($persons) > 1;

        return [
            'assigned_persons' => $persons,
            'is_joint_assignment' => $isJoint,
            'role_title' => $hasFormalTitle ? $formalTitle : null,
            'has_formal_title' => $hasFormalTitle,
            'responsibility_label' => $this->formatResponsibilityLabel($category, $rolePart),
            'description' => trim($rolePart, " .\t\n\r"),
            'category' => $category,
            'source_slot' => $slot,
            'source_document' => $docName,
            'source_page' => $page,
            'supporting_text' => $fullLine,
        ];
    }

    /**
     * Check if a string looks like person names rather than a section title or clause.
     */
    protected function looksLikePersonName(string $text): bool
    {
        $clean = trim($text);
        if (strlen($clean) > 80) return false;
        return (bool) preg_match('/^[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*(?:\s*(?:and|&|,)\s*[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)*$/', $clean);
    }

    /**
     * Non-person organizational and section keywords to ignore.
     */
    protected const NON_PERSON_KEYWORDS = [
        'deliverables', 'primary responsibilities', 'responsibilities', 'reports to',
        'daily standup', 'sprint planning', 'sprint review', 'retrospective',
        'total core team size', 'architecture review board', 'section', 'document',
        'version', 'classification', 'department', 'phase', 'milestone', 'key deliverable',
        'project overview', 'leadership & architecture', 'infrastructure & security',
        'design & user experience', 'team cadence and governance', 'core team size',
        'team size', 'both', 'all', 'etc', 'team', 'members', 'leadership',
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    /**
     * Extract individual person names, recognizing joint assignments with 'and', '&', ',', 'jointly'.
     */
    public function extractPersonNames(string $text): array
    {
        $clean = trim($text);

        // Strip prefixes like "Taken by", "Assigned to", "Led by"
        $clean = preg_replace('/^(?:taken\s+by|assigned\s+(?:jointly\s+)?to|handled\s+by|led\s+by)\s+/i', '', $clean);
        // Strip trailing punctuation
        $clean = trim($clean, " .\t\n\r-:");

        // Split on 'and', '&', or comma
        $rawParts = preg_split('/\s+(?:and|&)\s+|,\s*/i', $clean);
        $names = [];

        foreach ($rawParts as $part) {
            $p = trim($part);
            $lower = strtolower($p);

            // Ignore empty or non-person organizational phrases
            if (empty($p) || in_array($lower, self::NON_PERSON_KEYWORDS)) {
                continue;
            }

            $isNonPerson = false;
            foreach (self::NON_PERSON_KEYWORDS as $np) {
                if (str_contains($lower, $np)) {
                    $isNonPerson = true;
                    break;
                }
            }
            if ($isNonPerson) {
                continue;
            }

            // Ensure capitalization and minimum length
            if (preg_match('/^[A-Z][a-zA-Z\.\'-]+(?:\s+[A-Z][a-zA-Z\.\'-]+)*$/', $p)) {
                $names[] = $p;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Detect if a formal title is explicitly mentioned.
     */
    public function detectFormalTitle(string $text): ?string
    {
        $lower = strtolower($text);

        foreach (self::FORMAL_TITLES as $title) {
            if (preg_match('/\b' . preg_quote($title, '/') . '\b/i', $lower)) {
                // Find matching title substring with proper casing
                preg_match('/\b' . preg_quote($title, '/') . '\b/i', $text, $m);
                return ucwords($m[0] ?? $title);
            }
        }

        return null;
    }

    /**
     * Detect functional category of an assignment.
     */
    public function detectCategory(string $text): string
    {
        $lower = strtolower($text);

        foreach (self::CATEGORY_KEYWORDS as $cat => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    return $cat;
                }
            }
        }

        return 'general';
    }

    /**
     * Create a user-friendly responsibility label.
     */
    protected function formatResponsibilityLabel(string $category, string $text): string
    {
        return match($category) {
            'technical' => 'Technical responsibilities',
            'product' => 'Product responsibilities',
            'marketing' => 'Marketing & Growth responsibilities',
            'management' => 'Management & Leadership responsibilities',
            'operations' => 'Operations responsibilities',
            'financial' => 'Financial responsibilities',
            'qa' => 'QA & Security responsibilities',
            default => 'Assigned duties',
        };
    }

    /**
     * Generate direct answer to user queries regarding roles, technical assignments, or member counts.
     */
    public function answerRoleQuery(string $query, array $extractions, string $docName = 'Document 2'): ?array
    {
        $lower = strtolower($query);
        $assignments = $extractions['assignments'] ?? [];
        $distinctMembers = $extractions['distinct_members'] ?? [];
        $explicitCount = $extractions['explicit_member_count'] ?? count($distinctMembers);

        if (empty($assignments) && empty($distinctMembers)) {
            return null;
        }

        // Case 1: "How many members are there" / Member Count inquiry
        if (preg_match('/\b(?:how\s+many\s+members|member\s+counts?|count\s+(?:of\s+)?members|total\s+(?:number\s+of\s+)?members)\b/i', $lower)) {
            $countText = "There are **{$explicitCount} members** identified in {$docName}.";
            if (!empty($distinctMembers)) {
                $countText .= "\n\n**Team Members:**\n" . implode("\n", array_map(fn($m) => "- {$m}", $distinctMembers));
            }

            $primaryCitation = !empty($assignments) ? [
                'docLabel' => 'Document B',
                'page' => $assignments[0]['source_page'] ?? 1,
                'quote' => $assignments[0]['supporting_text'] ?? "Total Core Team Size: {$explicitCount}",
            ] : null;

            return [
                'direct_answer' => $countText,
                'citation' => $primaryCitation,
            ];
        }

        // Case 2: Individual roles and member count request
        // e.g. "Give me individual roles and how many members are there."
        if (preg_match('/\b(?:individual\s+roles|roles\s+and\s+(?:how\s+many|member)|list\s+(?:all\s+)?(?:the\s+)?roles)\b/i', $lower)) {
            $lines = [];
            $lines[] = "There are **{$explicitCount} members** in {$docName}:\n";

            foreach ($assignments as $a) {
                $persons = implode(' and ', $a['assigned_persons']);
                $roleLabel = $a['has_formal_title'] ? "**{$a['role_title']}**" : "**{$a['responsibility_label']}**";
                $titleNote = $a['has_formal_title'] ? "" : " *(operational responsibility, no formal executive title specified)*";
                $lines[] = "- **{$persons}**: {$roleLabel}{$titleNote} — {$a['description']}";
            }

            $firstPage = !empty($assignments) ? ($assignments[0]['source_page'] ?? 1) : 1;
            $firstQuote = !empty($assignments) ? ($assignments[0]['supporting_text'] ?? '') : '';

            return [
                'direct_answer' => implode("\n", $lines),
                'citation' => [
                    'docLabel' => 'Document B',
                    'page' => $firstPage,
                    'quote' => $firstQuote,
                ],
            ];
        }

        // Case 3: Specific functional role query (e.g. "Who is taking the technical role in Document 2?")
        $targetCategory = null;
        if (preg_match('/\b(?:technical|technology|tech|camera|web\s+app|blockchain|software|engineering|code)\b/i', $lower)) {
            $targetCategory = 'technical';
        } elseif (preg_match('/\b(?:product|vision|roadmap|design|ui\/ux)\b/i', $lower)) {
            $targetCategory = 'product';
        } elseif (preg_match('/\b(?:marketing|growth|user\s+acquisition|sales)\b/i', $lower)) {
            $targetCategory = 'marketing';
        } elseif (preg_match('/\b(?:operations|operational|logistics|standup)\b/i', $lower)) {
            $targetCategory = 'operations';
        } elseif (preg_match('/\b(?:management|lead|leadership|founder|director)\b/i', $lower)) {
            $targetCategory = 'management';
        } elseif (preg_match('/\b(?:finance|financial|budget|accounting)\b/i', $lower)) {
            $targetCategory = 'financial';
        } elseif (preg_match('/\b(?:qa|quality|testing|security|compliance)\b/i', $lower)) {
            $targetCategory = 'qa';
        }

        if ($targetCategory !== null) {
            $matched = array_values(array_filter($assignments, fn($a) => $a['category'] === $targetCategory));
            if (!empty($matched)) {
                $match = $matched[0];
                $persons = $match['assigned_persons'];
                $isJoint = count($persons) > 1;
                $personStr = implode(' and ', $persons);

                $titleExplanation = $match['has_formal_title']
                    ? "with the title of **{$match['role_title']}**"
                    : "*(Note: The document specifies these operational responsibilities directly with no formal job title (such as CTO) established.)*";

                $prefix = !empty($docName) ? "In {$docName}, " : "";
                if ($isJoint) {
                    $direct = "{$prefix}the {$match['category']} responsibilities are assigned jointly to **{$personStr}**. Their responsibilities include {$match['description']}.\n\n{$titleExplanation}";
                } else {
                    $direct = "{$prefix}the {$match['category']} responsibilities are assigned to **{$personStr}**. Their responsibilities include {$match['description']}.\n\n{$titleExplanation}";
                }

                return [
                    'direct_answer' => $direct,
                    'citation' => [
                        'docLabel' => 'Document B',
                        'page' => $match['source_page'] ?? 1,
                        'quote' => $match['supporting_text'] ?? "{$personStr}: {$match['description']}",
                    ],
                ];
            }
        }

        // Case 4: Query about a specific person (e.g. "What is Nihal's role?")
        foreach ($distinctMembers as $member) {
            $memberLower = strtolower($member);
            $nameTokens = array_filter(explode(' ', $memberLower), fn($w) => strlen($w) > 2);
            $matched = str_contains($lower, $memberLower);
            if (!$matched) {
                foreach ($nameTokens as $token) {
                    if (preg_match('/\b' . preg_quote($token, '/') . '\b/i', $lower)) {
                        $matched = true;
                        break;
                    }
                }
            }

            if ($matched) {
                $personMatches = array_filter($assignments, fn($a) => in_array($member, $a['assigned_persons']));
                if (!empty($personMatches)) {
                    $pm = array_values($personMatches)[0];
                    $jointNote = $pm['is_joint_assignment'] ? " (assigned jointly with " . implode(', ', array_diff($pm['assigned_persons'], [$member])) . ")" : "";
                    $prefix = !empty($docName) ? "In {$docName}, " : "";
                    $direct = "{$prefix}**{$member}** is responsible for: {$pm['description']}{$jointNote}.";
                    return [
                        'direct_answer' => $direct,
                        'citation' => [
                            'docLabel' => 'Document B',
                            'page' => $pm['source_page'] ?? 1,
                            'quote' => $pm['supporting_text'],
                        ],
                    ];
                }
            }
        }

        return null;
    }
}
