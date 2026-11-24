<?php

namespace App\Services\AI\Intent;

class TaskIntentClassifier
{
    /**
     * Common legal and business clause categories to identify in queries.
     */
    protected const CLAUSE_PATTERNS = [
        'termination' => ['termination', 'terminate', 'cancellation', 'cancel', 'default', 'cure period'],
        'payment' => ['payment', 'invoicing', 'invoice', 'fees', 'fee', 'pricing', 'price', 'rates', 'penalty', 'penalties', 'interest'],
        'liability' => ['liability', 'limitation of liability', 'liability cap', 'damages', 'indemnif', 'indemnity', 'hold harmless'],
        'confidentiality' => ['confidentiality', 'confidential', 'nda', 'trade secret', 'non-disclosure'],
        'ip' => ['intellectual property', 'ip rights', 'work for hire', 'copyright', 'ownership of work', 'patent'],
        'governing_law' => ['governing law', 'jurisdiction', 'arbitration', 'dispute resolution', 'venue', 'choice of law'],
        'warranty' => ['warranty', 'warranties', 'as is', 'representation', 'representations and warranties'],
        'roles' => ['role', 'roles', 'assigned', 'duty', 'duties', 'responsibilities', 'who does what', 'team member'],
        'schedule' => ['schedule', 'timeline', 'deadline', 'deadlines', 'milestone', 'milestones', 'time allocation', 'routine'],
        'force_majeure' => ['force majeure', 'act of god', 'unforeseen events'],
        'non_compete' => ['non-compete', 'non-solicitation', 'exclusivity', 'restrictive covenant'],
    ];

    /**
     * Classify user query or comparison focus prompt into a precise TaskIntentDTO.
     */
    public function classify(string $query): TaskIntentDTO
    {
        $clean = trim($query);
        $lower = strtolower($clean);

        // 1. Detect target slot (Doc A, Doc B, or Both)
        $targetSlot = 'both';
        $mentionsBoth = (bool) preg_match('/\b(?:both|these\s*2|two\s*doc(?:ument)?s?|difference\s*(?:of|between)|between\s+them|compare)\b/i', $clean);

        if (! $mentionsBoth) {
            if (preg_match('/\b(?:in|of|from|for|about|to)\s+(?:doc(?:u?e?ment)?\s*(?:a|1)|first\s+(?:document|agreement|summary)|the\s+first\s+(?:document|one|summary))\b/i', $clean) ||
                preg_match('/\b(?:doc(?:u?e?ment)?\s*(?:a|1)|first\s+(?:document|summary))\s+(?:only|\'s|:|liability|payment|termination|maintenance)\b/i', $clean)) {
                $targetSlot = 'A';
            } elseif (preg_match('/\b(?:in|of|from|for|about|to)\s+(?:doc(?:u?e?ment)?\s*(?:b|2)|second\s+(?:document|agreement|summary)|the\s+second\s+(?:document|one|summary)|revised\s+(?:document|draft))\b/i', $clean) ||
                preg_match('/\b(?:doc(?:u?e?ment)?\s*(?:b|2)|second\s+(?:document|summary))\s+(?:only|\'s|:|liability|payment|termination|maintenance)\b/i', $clean) ||
                preg_match('/\b(?:in|of)\s+doc(?:u?e?ment)?\s*b\b/i', $clean)) {
                $targetSlot = 'B';
            }
        }

        // 2. Detect section or clause numbers (e.g. "section 4", "clause 14.2", "page 2", "article 3")
        $targetSections = [];
        if (preg_match_all('/\b(?:section|sec\.?|clause|article|art\.?)\s*([0-9]+(?:\.[0-9]+)*|[a-z]|[ivxlcdm]+)\b/i', $clean, $matches)) {
            foreach ($matches[1] as $idx => $num) {
                $targetSections[] = trim($num);
                $targetSections[] = trim($matches[0][$idx]);
            }
            $targetSections = array_unique($targetSections);
        }

        if (preg_match_all('/\b(?:page|p\.?)\s*([0-9]+)\b/i', $clean, $pMatches)) {
            foreach ($pMatches[0] as $pm) {
                $targetSections[] = strtolower(trim($pm));
            }
        }

        // 3. Detect target clauses / legal concepts (only if document referenced or query mentions them)
        $targetClauses = [];
        foreach (self::CLAUSE_PATTERNS as $clauseCategory => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $targetClauses[] = $kw;
                    $targetClauses[] = str_replace('_', ' ', $clauseCategory);
                    break;
                }
            }
        }
        $targetClauses = array_values(array_unique($targetClauses));

        // 4. Detect Explicit Constraints & Output Formatting
        $constraints = [];

        // Exact bullet points (e.g. "in 3 points", "5 bullet points", "give me 5 project ideas")
        if (preg_match('/\b(\d+|one|two|three|four|five|six|seven|eight|nine|ten)\s+(?:bullet\s*)?(?:points?|items?|ideas?|suggestions?|examples?)\b/i', $clean, $bMatch)) {
            $numMap = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10];
            $bNum = is_numeric($bMatch[1]) ? (int) $bMatch[1] : ($numMap[strtolower($bMatch[1])] ?? 5);
            $constraints[] = "exact_bullets:{$bNum}";
        } elseif (preg_match('/\b(?:in\s+bullet\s+points|bulleted\s+list|as\s+bullets)\b/i', $clean)) {
            $constraints[] = 'format_bullet_list';
        }

        // Sentence constraints
        if (preg_match('/\b(?:in\s+one\s+sentence|in\s+a\s+single\s+sentence|answer\s+in\s+one\s+sentence|only\s+one\s+sentence|give\s+me\s+the\s+answer\s+in\s+one\s+sentence)\b/i', $clean)) {
            $constraints[] = 'exact_sentences:1';
        } elseif (preg_match('/\b(?:exactly\s+)?(\d+|one|two|three|four|five)\s+sentences?\b/i', $clean, $sMatch)) {
            $numMap = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5];
            $sNum = is_numeric($sMatch[1]) ? (int) $sMatch[1] : ($numMap[strtolower($sMatch[1])] ?? 3);
            $constraints[] = "exact_sentences:{$sNum}";
        }

        // Table formatting
        if (preg_match('/\b(?:create\s+a\s+table|into\s+a\s+table|in\s+a\s+table|compare\s+(?:them\s+)?in\s+a\s+table|comparison\s+table|markdown\s+table|tabular\s+format|show\s+(?:the\s+)?differences\s+in\s+a\s+table)\b/i', $clean)) {
            $constraints[] = 'format_markdown_table';
        }

        // Simplicity
        if (preg_match('/\b(?:in\s+simple\s+(?:language|terms|words)|explain\s+simply|simple\s+comparison|simple\s+english|accessible\s+language)\b/i', $clean)) {
            $constraints[] = 'simple_language';
        }

        // Detail
        if (preg_match('/\b(?:detailed\s+comparison|in\s+detail|expand\s+the\s+comparison|thorough\s+comparison)\b/i', $clean)) {
            $constraints[] = 'detailed_comparison';
        }

        // Forbid summary
        if (preg_match('/\b(?:do\s+not\s+summarize|don\'t\s+summarize|without\s+summarizing)\b/i', $clean)) {
            $constraints[] = 'forbid_summary';
        }

        // Forbid recommendations
        if (preg_match('/\b(?:do\s+not\s+give\s+recommendations?|don\'t\s+give\s+recommendations?|no\s+recommendations?|without\s+recommendations?|exclude\s+recommendations?)\b/i', $clean)) {
            $constraints[] = 'forbid_recommendations';
        }

        // JSON only
        if (preg_match('/\b(?:return\s+(?:the\s+)?result\s+as\s+json\s+only|json\s+only|as\s+json\s+only|format\s+as\s+json\s+only|only\s+json)\b/i', $clean)) {
            $constraints[] = 'json_only';
        }

        // Yes/No only
        if (preg_match('/\b(?:answer\s+only\s+yes\s+or\s+no|yes\s+or\s+no\s+only|only\s+yes\s+or\s+no)\b/i', $clean)) {
            $constraints[] = 'yes_no_only';
        }

        // Strict extraction constraints
        if (preg_match('/\b(?:extract\s+only\s+(?:the\s+)?names|only\s+(?:the\s+)?names|names\s+only|extract\s+the\s+names\s+only|only\s+names)\b/i', $clean)) {
            $constraints[] = 'only_names';
            $constraints[] = 'strict_extraction_only';
        }

        if (preg_match('/\b(?:extract\s+(?:the\s+)?dates\s+only|only\s+(?:the\s+)?dates|dates\s+only|extract\s+only\s+(?:the\s+)?dates)\b/i', $clean)) {
            $constraints[] = 'only_dates';
            $constraints[] = 'strict_extraction_only';
        }

        if (preg_match('/\b(?:give\s+me\s+only\s+(?:the\s+)?(?:final\s+)?amount|only\s+(?:the\s+)?(?:final\s+)?amount|amount\s+only|final\s+amount\s+only|only\s+the\s+final\s+amount)\b/i', $clean)) {
            $constraints[] = 'only_amount';
            $constraints[] = 'strict_extraction_only';
        }

        if (preg_match('/\b(?:only\s+show\s+(?:the\s+)?numerical\s+differences|numerical\s+differences\s+only|only\s+(?:the\s+)?numerical\s+differences)\b/i', $clean)) {
            $constraints[] = 'only_numerical';
            $constraints[] = 'only_differences';
        } elseif (preg_match('/\b(?:show\s+only\s+(?:the\s+)?differences|only\s+(?:the\s+)?differences)\b/i', $clean)) {
            $constraints[] = 'only_differences';
        }

        if (preg_match('/\b(?:compare\s+only\s+(?:the\s+)?responsibilities|responsibilities\s+only|only\s+(?:the\s+)?responsibilities)\b/i', $clean)) {
            $constraints[] = 'only_responsibilities';
        }

        if (preg_match('/\b(?:make\s+(?:the\s+previous\s+response|it|this|that|response)\s+shorter|make\s+shorter|make\s+it\s+shorter)\b/i', $clean)) {
            $constraints[] = 'make_shorter';
        }

        if (preg_match('/\b(?:extract\s+only|only\s+the\s+dates\s+and\s+amounts|dates\s+and\s+amounts\s+only)\b/i', $clean)) {
            $constraints[] = 'strict_extraction_only';
        }

        if (preg_match('/\b(?:without\s+discussing\s+(?:the\s+)?other\s+(?:clauses|sections))\b/i', $clean)) {
            $constraints[] = 'strict_section_only';
        }

        if (preg_match('/\b(?:ignore\s+(?:the\s+)?previous\s+task|disregard\s+(?:the\s+)?previous\s+task|instead\s+perform\s+this\s+new\s+task|perform\s+this\s+new\s+task\s+instead)\b/i', $clean)) {
            $constraints[] = 'override_previous_task';
        }

        if (preg_match('/\b(?:without\s+changing\s+(?:its\s+)?meaning|preserving\s+(?:its\s+)?meaning|preserve\s+(?:exact\s+)?meaning|same\s+meaning|exact\s+meaning)\b/i', $clean)) {
            $constraints[] = 'preserve_exact_meaning';
        }

        // 5. Document Reference & Upload Capabilities Detection
        $hasDocReference = (bool) preg_match('/\b(?:doc|docs|document|documents|docuement|docuements|docuemnt|docuemnts|pdf|file|files|agreement|agreements|contract|contracts|section|sections|clause|clauses|article|articles|page|pages|uploaded|in\s+this|between\s+these|these\s+2|both\s+documents|doc\s*[ab]|docu?e?ments?\s*[ab]|schedule|roles?|responsibilit(?:y|ies)|duties|team\s+members?|who\s+does\s+what|who\s+handles|who\s+is\s+taking|taking\s+the|member\s+counts?)\b/i', $clean);

        $isUploadInquiry = (bool) preg_match('/\b(?:how\s+much\s+(?:gb|mb|storage|files?)|upload\s+(?:limit|limits|size|capacity)|what\s+(?:size|files?|formats?)\s+(?:can\s+i|to)\s+upload|supported\s+(?:files?|formats?|extensions?|types?)|can\s+i\s+upload|file\s+uploads?|how\s+many\s+files?)\b/i', $clean);

        $isAttribution = (bool) preg_match('/\b(?:which\s+(?:document|doc|file|one)\s+(?:contains?|has|mentions?|explains?|details?|describes?)|where\s+(?:is|are)\s+(?:the\s+)?(?:daily\s+routine|team\s+responsibilities|roles))\b/i', $clean);

        $isRoleInquiry = !$isAttribution && (bool) preg_match('/\b(?:who\s+(?:is|are)\s+(?:taking|handling|responsible\s+for|in\s+charge\s+of|managing|leading)|who\s+handles|who\s+leads|who\s+takes|roles?|assigned|duties|responsibilities|team\s+members?|who\s+does\s+what|how\s+many\s+members|individual\s+roles|member\s+counts?|what\s+is\s+[a-z\s]+\'?s\s+role|role\s+of\s+[a-z\s]+)\b/i', $clean);

        $isAuditReport = (bool) preg_match('/\b(?:full\s+audit|detailed\s+comparison|verification\s+report|clause-by-clause\s+analysis|clause\s+by\s+clause|evidence\s+report|enterprise\s+audit|audit\s+report|generate\s+(?:a\s+)?formal\s+report)\b/i', $clean);
        if ($isAuditReport) {
            $constraints[] = 'formal_verification_report';
        } else {
            $constraints[] = 'simple_comparison_summary';
            $constraints[] = 'forbid_unnecessary_audit';
        }

        if ($isRoleInquiry) {
            if (preg_match('/\b(?:who\s+(?:is|are)\s+(?:taking|handling|responsible|managing|leading)|who\s+handles|who\s+takes)\b/i', $clean)) {
                $constraints[] = 'direct_answer_first';
            }
            if (preg_match('/\b(?:how\s+many\s+members|member\s+counts?|count\s+(?:of\s+)?members|total\s+members?)\b/i', $clean)) {
                $constraints[] = 'count_members';
            }
            if (preg_match('/\b(?:technical|technology|tech|camera|web\s+app|blockchain|software|code)\b/i', $clean)) {
                $constraints[] = 'technical_role';
            }
        }

        // 6. General Purpose Chatbot Intent Detection (Coding, Writing, Knowledge, Brainstorming, Problem Solving, Conversation)
        $isFactualQuery = (bool) preg_match('/^(?:where\s+is|what\s+is\s+the\s+capital|who\s+is|who\s+was|when\s+was|when\s+did|what\s+is\s+the\s+(?:currency|population|height|size)|which\s+country|what\s+year)\b/i', $clean);
        $isWritingAssistance = (bool) preg_match('/^(?:write\s+an?\s+(?:email|letter|memo|message|note|cover\s+letter|essay|post)|draft\s+an?\s+(?:email|letter|memo|message)|compose\s+an?\s+(?:email|letter))\b/i', $clean);
        $isCodingAssistance = (bool) preg_match('/\b(?:python|javascript|typescript|java|c\+\+|php|sql|html|css|code|function|loop|loops|algorithm|regex|programming|script|class|variable)\b/i', $clean) &&
            (bool) preg_match('/\b(?:explain|write|code|program|example|how\s+to|fix|debug|create)\b/i', $clean);
        $isBrainstorming = (bool) preg_match('/\b(?:give\s+me|generate|list|brainstorm|suggest)\s+(?:\d+\s+)?(?:ideas|project\s+ideas|topics|names|options|tips|ways|suggestions)\b/i', $clean);
        $isTutoringOrConcept = (bool) preg_match('/^(?:what\s+is\s+artificial\s+intelligence|explain\s+(?:what\s+)?(?:[a-z0-9\s_-]+?)(?:\s+means?|\s+in\s+simple\s+(?:english|terms|words)|\?|$)|what\s+does\s+(?:[a-z0-9\s_-]+?)\s+mean|how\s+does\s+[a-z\s]+\s+work|what\s+is\s+[a-z\s]+(?:\?|$))\b/i', $clean) && !$hasDocReference && !$isRoleInquiry;
        $isProblemSolving = (bool) preg_match('/\b(?:help\s+me\s+solve|solve\s+this|solve\s+for|how\s+do\s+i\s+solve|troubleshoot)\b/i', $clean);
        $isGreetingOrCasual = (bool) preg_match('/^(?:hi|hello|hey|greetings|good\s+(?:morning|afternoon|evening)|who\s+are\s+you|what\s+can\s+you\s+do|how\s+are\s+you|tell\s+me\s+a\s+joke)\b/i', $clean);

        $isFollowUpRefinement = (bool) preg_match('/\b(?:make\s+(?:the\s+)?(?:previous\s+)?(?:response|answer|it)\s+shorter|make\s+shorter|shorten|condense|simpler\s+words|more\s+concise|shorter|briefer|explain\s+that\s+again|explain\s+the\s+(?:first|second|third|fourth|last)\s+point)\b/i', $clean);

        $isGeneralChat = !$hasDocReference && !$isUploadInquiry && !$isRoleInquiry && !$isAttribution && !$isFollowUpRefinement && (
            $isFactualQuery ||
            $isWritingAssistance ||
            $isCodingAssistance ||
            $isBrainstorming ||
            $isTutoringOrConcept ||
            $isProblemSolving ||
            $isGreetingOrCasual
        );

        // 7. Determine Final Task Intent
        $intent = 'general_qa';
        $scope = ($targetSlot !== 'both') ? 'single_section' : 'cross_document';

        if ($isUploadInquiry) {
            $intent = 'upload_inquiry';
            $scope = 'platform';
            $constraints[] = 'authoritative_upload_specifications';
        } elseif ($isAttribution) {
            $intent = 'document_attribution';
            $scope = 'cross_document';
            $constraints[] = 'identify_containing_document';
        } elseif ($isRoleInquiry) {
            $intent = 'role_and_task_extraction';
            $scope = ($targetSlot !== 'both') ? 'single_section' : 'whole_document';
            $constraints[] = 'extract_itemized_roles';
        } elseif ($isGeneralChat) {
            $intent = 'general_chat';
            $scope = $isFactualQuery ? 'factual' : ($isWritingAssistance ? 'writing' : ($isCodingAssistance ? 'coding' : 'conceptual'));
            if ($isFactualQuery) {
                $constraints[] = 'direct_factual_answer';
            } elseif ($isWritingAssistance) {
                $constraints[] = 'writing_assistance';
            } elseif ($isCodingAssistance) {
                $constraints[] = 'coding_assistance';
            } elseif ($isBrainstorming) {
                $constraints[] = 'brainstorming_ideas';
            } else {
                $constraints[] = 'conversational_explanation';
            }
        } elseif (preg_match('/\b(?:are\s+(?:these|they|both)\s+(?:two\s+)?(?:versions|drafts|copies|the\s+same\s+document)|is\s+(?:doc(?:ument)?\s*[ab12]|one)\s+a\s+(?:revision|version|copy)\s+of|are\s+these\s+different\s+versions)\b/i', $clean)) {
            $intent = 'version_relationship_check';
            $scope = 'cross_document';
            $constraints[] = 'evaluate_version_relationship';
        } elseif (preg_match('/\b(?:which\s+(?:document|doc|file|one)\s+(?:contains?|has|mentions?|explains?|details?|describes?)|where\s+(?:is|are)\s+the\s+(?:daily\s+routine|team\s+responsibilities|roles))\b/i', $clean)) {
            $intent = 'document_attribution';
            $scope = 'cross_document';
            $constraints[] = 'identify_containing_document';
        } elseif (!in_array('override_previous_task', $constraints) && (
            (preg_match('/\b(?:make|shorten|condense|expand|explain\s+that\s+again|what\s+did\s+you\s+mean|simpler\s+words|more\s+concise|shorter|briefer)\b/i', $clean) &&
             (str_contains($lower, 'first') || str_contains($lower, 'second') || str_contains($lower, 'summary') || str_contains($lower, 'again') || str_contains($lower, 'point') || str_contains($lower, 'it') || str_contains($lower, 'previous') || str_contains($lower, 'answer'))) ||
            preg_match('/\b(?:explain\s+the\s+(?:first|second|third|fourth|last)\s+(?:point|item|bullet)|turn\s+it\s+into\s+a\s+table|convert\s+it\s+into\s+a\s+table|continue\s+from\s+where\s+you\s+stopped|make\s+the\s+previous\s+answer\s+shorter)\b/i', $clean)
        )) {
            $intent = 'follow_up_refinement';
            $scope = $targetSlot !== 'both' ? 'single_document' : 'conversation';
            $constraints[] = 'preserve_previous_context';
        } elseif (preg_match('/\b(?:extract\s+only\s+(?:the\s+)?dates\s+and\s+amounts|extract\s+dates\s+and\s+amounts|compare\s+(?:the\s+)?dates\s+and\s+amounts)\b/i', $clean)) {
            $intent = 'numerical_comparison';
            $scope = ($targetSlot !== 'both') ? 'single_document' : 'clause';
            $constraints[] = 'exact_numerical_audit';
            $constraints[] = 'strict_extraction_only';
        } elseif (((bool) preg_match('/\b(?:both|two|these\s+2|each|separately)\b/i', $clean) && (bool) preg_match('/\b(?:summarize|summary|overview|breakdown)\b/i', $clean)) ||
            (bool) preg_match('/\bsummarize\s+(?:both|these\s+two|all|the\s+documents|each)\b/i', $clean)) {
            $intent = 'multi_document_summary';
            $scope = 'multi_document';
            $targetSlot = 'both';
            $constraints[] = 'independent_summaries_no_mixing';
        } elseif (preg_match('/\b(?:role|roles|assigned|duties|responsibilities|team\s+member|who\s+does\s+what)\b/i', $clean)) {
            $intent = 'role_and_task_extraction';
            $scope = $targetSlot !== 'both' ? 'single_section' : 'whole_document';
            $constraints[] = 'extract_itemized_roles';
        } elseif ($isStrictSection = ((bool) preg_match('/\b(?:only|exclusively|specifically|focus\s+on)\s+(?:section|clause|article|page)\b/i', $clean) ||
            (!empty($targetSections) && (str_contains($lower, 'only') || str_contains($lower, 'summarize only') || str_contains($lower, 'without discussing'))))) {
            $intent = 'section_specific';
            $scope = 'single_section';
            $constraints[] = 'strict_section_only';
        } elseif (preg_match('/\b(?:explain|clarify|what\s+does.*mean|in\s+simple\s+(?:language|terms|words)|break\s+down\s+the\s+meaning|plain\s+english)\b/i', $clean)) {
            $intent = 'clause_explanation';
            $scope = 'clause';
            $constraints[] = 'explain_meaning_in_own_words';
        } elseif (preg_match('/\b(?:numerical|amount|amounts|price|pricing|fee|fees|cost|rate|rates|percentage|percent|period|periods|days|duration|dollar|\$|limit|cap|monetary)\b/i', $clean)) {
            $intent = 'numerical_comparison';
            $scope = ($targetSlot !== 'both') ? 'single_document' : 'clause';
            $constraints[] = 'exact_numerical_audit';
        } elseif (preg_match('/\b(?:missing|omitted|removed|deleted|newly\s+added|added|unmatched)\b/i', $clean)) {
            $intent = 'missing_clause';
            $scope = 'cross_document';
            $constraints[] = 'check_omissions';
        } elseif (preg_match('/\b(?:risk|risks|risk\s+analysis|risk\s+assessment|identify\s+(?:only\s+)?(?:the\s+)?risks|legal\s+risks?|potential\s+risks?)\b/i', $clean)) {
            $intent = 'risk_analysis';
            $scope = ($targetSlot !== 'both') ? 'single_document' : 'whole_document';
            $constraints[] = 'identify_itemized_risks';
        } elseif (!in_array('forbid_summary', $constraints) && preg_match('/\b(?:summarize|summary|overview|breakdown|list\s+all|what\s+is\s+this|what\s+are\s+the\s+items)\b/i', $clean)) {
            $intent = 'summarization';
            $scope = 'whole_document';
            $constraints[] = 'structured_summary_sections';
        } elseif (preg_match('/\b(?:create|draft|generate)\s+.*?\b(?:agreement|contract|nda|non-disclosure|policy|terms\s+of\s+service|privacy\s+policy|document|memo|proposal|charter|mou|template)\b/i', $clean)) {
            $intent = 'document_creation';
            $scope = 'creative';
            $constraints[] = 'complete_document_structure';
        } elseif (preg_match('/\b(?:rewrite|improve\s+grammar|rephrase|simplify\s+(?:the\s+)?(?:language|wording)|revise|make\s+more\s+professional|edit\s+this\s+(?:section|clause|paragraph)|modify\s+this\s+(?:section|clause|paragraph))\b/i', $clean)) {
            $isPreserve = in_array('preserve_exact_meaning', $constraints) || (bool) preg_match('/\b(?:without\s+changing\s+(?:its\s+)?meaning|preserving\s+(?:its\s+)?meaning|preserve\s+(?:exact\s+)?meaning|same\s+meaning|exact\s+meaning)\b/i', $clean);
            $intent = $isPreserve ? 'rewrite_preservation' : 'document_rewrite';
            $scope = $targetSlot !== 'both' ? 'single_section' : 'clause';
            $constraints[] = $isPreserve ? 'preserve_exact_meaning' : 'preserve_core_meaning';
        } elseif (preg_match('/\b(?:research\s+online|search\s+the\s+web|look\s+up\s+external|verify\s+externally|external\s+sources|find\s+online\s+information|web\s+search)\b/i', $clean)) {
            $intent = 'external_research';
            $scope = 'external';
            $constraints[] = 'cite_external_sources';
        } elseif (preg_match('/\b(?:compare|difference|differences|changes|modified|revision|revisions|versus|vs|what\s+is\s+different)\b/i', $clean)) {
            $intent = 'clause_diff';
            $scope = 'cross_document';
            $constraints[] = 'compare_exact_clauses';
        }

        // Always mandate negative reporting when absent
        $constraints[] = 'negative_report_if_absent';

        // Extract significant keywords
        $stopwords = ['the', 'and', 'for', 'with', 'what', 'between', 'these', 'this', 'that', 'from', 'about', 'show', 'tell', 'give', 'document', 'documents', 'docver', 'please'];
        $words = preg_split('/[^\w]+/', $lower, -1, PREG_SPLIT_NO_EMPTY);
        $keywords = array_values(array_unique(array_filter($words, fn($w) => strlen($w) > 2 && !in_array($w, $stopwords))));

        return new TaskIntentDTO(
            intent: $intent,
            rawQuery: $clean,
            targetSections: $targetSections,
            targetClauses: $targetClauses,
            targetSlot: $targetSlot,
            scope: $scope,
            constraints: $constraints,
            keywords: $keywords
        );
    }
}
