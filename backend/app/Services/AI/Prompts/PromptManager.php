<?php

namespace App\Services\AI\Prompts;

use App\Services\AI\Context\ContextResultDTO;
use App\Services\AI\Intent\TaskIntentDTO;

class PromptManager
{
    /**
     * Build appropriate system instruction and user prompt based on intent and context.
     *
     * @return array{systemInstruction: string, userPrompt: string}
     */
    public function buildPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName = '',
        array $conversationHistory = []
    ): array {
        // If the user explicitly asked to ignore the previous task and perform a new task instead,
        // do not let previous conversation history override the new instruction.
        $historyText = in_array('override_previous_task', $intent->constraints)
            ? ''
            : $this->formatConversationHistory($conversationHistory);

        $basePrompts = match ($intent->intent) {
            'general_chat' => $this->buildGeneralChatPrompt($intent->rawQuery, $historyText, $intent),
            'document_attribution' => $this->buildDocumentAttributionPrompt($intent, $context, $docAName, $docBName, $historyText),
            'version_relationship_check' => $this->buildVersionRelationshipCheckPrompt($intent, $context, $docAName, $docBName, $historyText),
            'follow_up_refinement' => $this->buildFollowUpPrompt($intent, $context, $docAName, $docBName, $historyText),
            'multi_document_summary' => $this->buildMultiDocumentSummaryPrompt($intent, $context, $docAName, $docBName, $historyText),
            'document_creation' => $this->buildDocumentCreationPrompt($intent, $context, $historyText),
            'document_rewrite', 'rewrite_preservation' => $this->buildDocumentRewritePrompt($intent, $context, $historyText),
            'external_research' => $this->buildExternalResearchPrompt($intent, $context, $docAName, $docBName, $historyText),
            'section_specific' => $this->buildSectionSpecificPrompt($intent, $context, $docAName, $docBName, $historyText),
            'clause_explanation' => $this->buildClauseExplanationPrompt($intent, $context, $docAName, $docBName, $historyText),
            'risk_analysis' => $this->buildRiskAnalysisPrompt($intent, $context, $docAName, $docBName, $historyText),
            'clause_diff' => $this->buildClauseDiffPrompt($intent, $context, $docAName, $docBName, $historyText),
            'numerical_comparison' => $this->buildNumericalPrompt($intent, $context, $docAName, $docBName, $historyText),
            'role_and_task_extraction' => $this->buildRoleExtractionPrompt($intent, $context, $docAName, $docBName, $historyText),
            'summarization' => $this->buildSummarizationPrompt($intent, $context, $docAName, $docBName, $historyText),
            'missing_clause' => $this->buildMissingClausePrompt($intent, $context, $docAName, $docBName, $historyText),
            'contradiction_check' => $this->buildContradictionPrompt($intent, $context, $docAName, $docBName, $historyText),
            default => $this->buildGeneralQAPrompt($intent, $context, $docAName, $docBName, $historyText),
        };

        $formatRules = $this->buildConstraintDirectives($intent);
        if (!empty($formatRules)) {
            $basePrompts['systemInstruction'] .= "\n\nSTRICT OUTPUT FORMAT & CONSTRAINT MANDATES:\n" . $formatRules;
        }

        return $basePrompts;
    }

    /**
     * Build explicit constraint directives for bullet count, sentence count, tables, and exclusions.
     */
    public function buildConstraintDirectives(TaskIntentDTO $intent): string
    {
        $directives = [
            "- UNIVERSAL DIRECTIVE: Answer ONLY what the user explicitly requested. Do NOT add unrequested executive summaries, introductions, conclusions, recommendations, legal disclaimers, or risk assessments.",
        ];

        foreach ($intent->constraints as $c) {
            if (str_starts_with($c, 'exact_bullets:')) {
                $count = (int) substr($c, 14);
                $directives[] = "- OUTPUT FORMAT: Format your response as a bulleted list with EXACTLY {$count} bullet points (`- `). Do not add headings, introductory text, or concluding paragraphs.";
            } elseif ($c === 'format_bullet_list') {
                $directives[] = "- OUTPUT FORMAT: Format your response as a concise bulleted list (`- `).";
            } elseif (str_starts_with($c, 'exact_sentences:')) {
                $count = (int) substr($c, 16);
                $directives[] = "- LENGTH CONSTRAINT: Answer in EXACTLY {$count} complete sentence" . ($count === 1 ? '' : 's') . ". Do not use bullet points, headings, or extra sentences.";
            } elseif ($c === 'format_markdown_table') {
                $directives[] = "- OUTPUT FORMAT: Present the extracted or compared information in a clean Markdown Table (`| Column | ... |`).";
            } elseif ($c === 'forbid_summary') {
                $directives[] = "- NEGATIVE CONSTRAINT: Do NOT provide a general document summary or overview. Output ONLY the specific items requested.";
            } elseif ($c === 'forbid_recommendations') {
                $directives[] = "- NEGATIVE CONSTRAINT: Strictly DO NOT provide recommendations, suggestions, advice, next steps, or action items.";
            } elseif ($c === 'only_differences') {
                $directives[] = "- SCOPE CONSTRAINT: Show ONLY the differences between the documents. Do NOT list matching or identical clauses.";
            } elseif ($c === 'only_numerical') {
                $directives[] = "- SCOPE CONSTRAINT: Output ONLY numerical differences (dates, monetary amounts, fees, percentages, deadlines). Do NOT discuss general or non-numerical clauses.";
            } elseif ($c === 'only_responsibilities') {
                $directives[] = "- SCOPE CONSTRAINT: Compare ONLY responsibilities, duties, and assigned tasks. Do NOT discuss payment terms, termination, or unrelated clauses.";
            } elseif ($c === 'only_names') {
                $directives[] = "- EXTRACTION CONSTRAINT: Extract ONLY the personal and organizational names found in the document. Return ONLY the names. Do NOT provide background explanations, roles, summaries, or recommendations.";
            } elseif ($c === 'only_dates') {
                $directives[] = "- EXTRACTION CONSTRAINT: Extract ONLY the dates found in the document. Return ONLY the dates. Do NOT provide summaries, background explanations, or recommendations.";
            } elseif ($c === 'only_amount') {
                $directives[] = "- EXTRACTION CONSTRAINT: Output ONLY the final monetary amount and its currency. Do NOT provide calculation commentary or recommendations.";
            } elseif ($c === 'strict_extraction_only') {
                $directives[] = "- EXTRACTION CONSTRAINT: Extract ONLY the requested entities. Do not include unrelated commentary or analysis.";
            } elseif ($c === 'json_only') {
                $directives[] = "- OUTPUT FORMAT: Output valid parseable JSON ONLY. Do NOT wrap with markdown backticks (```json) and do NOT include any introductory or concluding text.";
            } elseif ($c === 'yes_no_only') {
                $directives[] = "- OUTPUT FORMAT: Answer ONLY 'Yes.' or 'No.' if the available evidence supports it. Do NOT include lengthy explanations.";
            } elseif ($c === 'make_shorter') {
                $directives[] = "- LENGTH CONSTRAINT: Make the response substantially shorter and more concise while preserving its core facts.";
            } elseif ($c === 'direct_factual_answer') {
                $directives[] = "- SCOPE CONSTRAINT: Provide a direct, concise factual answer to the question. Do NOT add conversational filler, disclaimers, or unrequested context.";
            } elseif ($c === 'simple_language') {
                $directives[] = "- TONE & STYLE: Explain the comparison in simple, everyday language without technical legal jargon.";
            } elseif ($c === 'detailed_comparison') {
                $directives[] = "- LEVEL OF DETAIL: Provide a comprehensive comparison with thorough explanations, evidence quotes, and citations for all key differences.";
            } elseif ($c === 'preserve_exact_meaning') {
                $directives[] = "- REWRITE CONSTRAINT: Rewrite the text for clarity while strictly preserving 100% of the original legal meaning, figures, dates, and obligations.";
            } elseif ($c === 'override_previous_task') {
                $directives[] = "- TASK OVERRIDE: Disregard any previous task instructions from earlier turns and execute ONLY the new task in the current user prompt.";
            }
        }

        return implode("\n", $directives);
    }

    /**
     * Document Creation / Drafting Prompt.
     */
    public function buildDocumentCreationPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $historyText
    ): array {
        $systemInstruction = <<<SYS
You are DocVer's Expert Legal & Document Drafting Specialist.
Your task is to draft a clean, professional, fully structured document adhering strictly to the user's instructions.

DRAFTING PRINCIPLES:
1. STRUCTURE & COMPLETENESS:
   - Provide a formal title, preamble/recitals, numbered articles/sections, definitions, operative provisions, term & termination, dispute resolution/governing law, and execution signature blocks.
2. PLACEHOLDERS FOR UNKNOWN DETAILS:
   - Identify missing factual details (names, dates, amounts, addresses) clearly with bracketed uppercase placeholders like [COMPANY NAME], [EFFECTIVE DATE], [STATE/JURISDICTION], [PAYMENT AMOUNT].
3. SOURCE MATERIAL UTILIZATION:
   - If reference documents were provided, incorporate relevant provisions, terms, and requirements accurately.
   - If drafting from scratch, use standard professional and legally sound boilerplate.
4. NOTICE:
   - Conclude with a brief standard notice: "Notice: Draft generated by DocVer AI. Independent legal and commercial review recommended prior to execution."
SYS;

        $userPrompt = "{$historyText}";
        if ($context->targetFound && !empty($context->formattedContext)) {
            $userPrompt .= "Reference Source Context:\n{$context->formattedContext}\n\n";
        }
        $userPrompt .= "User Drafting Request: {$intent->rawQuery}\n\n" .
            "Generate the full structured draft now in clear, professional markdown.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * External Research & Benchmark Verification Prompt
     */
    public function buildExternalResearchPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $systemInstruction = <<<SYS
You are DocVer AI, specialized in external research, market benchmarks, and comparative knowledge synthesis.

CORE RESEARCH PROTOCOL:
1. Clearly distinguish external research/knowledge from uploaded document facts.
2. If citing industry standards, external regulations, or market rates, label them explicitly as external references (e.g. "[External Standard/Market Data]").
3. If uploaded documents are provided, objectively compare the external findings against the document's terms.
4. Do not invent non-existent statistics or pretend to have accessed dynamic live private databases.
5. Provide objective, well-structured research findings matching the requested depth and format.
SYS;

        $userPrompt = "{$historyText}User Research Request: {$intent->rawQuery}";
        if ($context->targetFound && !empty($context->formattedContext)) {
            $userPrompt .= "\n\nAttached Document Excerpts for Cross-Reference:\n{$context->formattedContext}";
        }

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * Document Rewrite / Polishing Prompt.
     */
    public function buildDocumentRewritePrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $historyText
    ): array {
        $systemInstruction = <<<SYS
You are DocVer's Professional Document Editor and Legal Stylist.
Your task is to rewrite, refine, or polish the provided text according to the user's specific instructions (e.g. improve clarity, correct grammar, simplify complex legalese, or make tone more formal/professional) without changing its meaning.

EDITING PRINCIPLES:
1. SUBSTANTIVE ACCURACY:
   - Preserve all core legal, operational, and commercial commitments (dates, amounts, timelines, obligations, conditions) unless the user explicitly requested a factual modification.
2. CLARITY & PRECISION:
   - Eliminate redundant phrasing, ambiguity, and poor grammar while elevating clarity.
3. STRUCTURED OUTPUT:
   - Present the **Revised Text** clearly.
   - Follow with a concise **Summary of Enhancements** explaining what was changed and why.
SYS;

        $userPrompt = "{$historyText}";
        if ($context->targetFound && !empty($context->formattedContext)) {
            $userPrompt .= "Original Document Excerpt:\n{$context->formattedContext}\n\n";
        }
        $userPrompt .= "Rewrite Instruction: {$intent->rawQuery}\n\n" .
            "Provide the revised version followed by a concise summary of enhancements.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * 1. Section-Specific Prompt (e.g. "Summarize only section 4")
     */
    protected function buildSectionSpecificPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $targetStr = !empty($intent->targetSections) ? implode(', ', $intent->targetSections) : 'specified section';
        $availableStr = !empty($context->availableSections) ? implode(', ', $context->availableSections) : 'none detected';

        $systemInstruction = <<<SYS
You are DocVer, an intelligent AI Document Assistant & Verification Specialist.
You are analyzing:
- Document: "{$docAName}"
SYS;
        if (!empty($docBName)) {
            $systemInstruction .= "\n- Counter-Draft: \"{$docBName}\"";
        }

        $systemInstruction .= <<<SYS


CRITICAL MANDATE FOR SECTION-SPECIFIC INSTRUCTION:
1. STRICT SCOPE CONFINEMENT:
   - The user has explicitly instructed to focus ONLY on {$targetStr}.
   - Do NOT summarize, discuss, or speculate on any other sections unless strictly needed to clarify a reference within {$targetStr}.
   - Preserve all specific names, dates, numbers, and operational details mentioned within {$targetStr}.
2. NEGATIVE REPORTING IF SECTION IS MISSING:
   - If {$targetStr} DOES NOT EXIST in the provided document text:
     * Directly and explicitly state: "This document does not contain {$targetStr}."
     * State which sections/headings actually exist in the document instead: [{$availableStr}].
     * Never invent or hallucinate contents for a missing section.
3. GROUNDING:
   - Base every statement strictly on the provided context excerpts. Quote exact text and cite page numbers.
SYS;

        $userPrompt = "{$historyText}Document Context Excerpts:\n{$context->formattedContext}\n\n" .
            "Exact User Instruction: {$intent->rawQuery}\n\n" .
            "Provide a focused, accurate answer addressing ONLY {$targetStr}.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * Document Attribution / Identification Prompt (e.g. "Which document contains the daily routine?")
     */
    protected function buildDocumentAttributionPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $systemInstruction = <<<SYS
You are DocVer, an intelligent Document Assistant.
You have access to two uploaded documents:
- Document A: "{$docAName}"
- Document B: "{$docBName}"

TASK: Identify which document contains the subject requested by the user.

RULES:
1. DIRECT IDENTIFICATION: State clearly which document (Document A or Document B) contains the requested subject.
2. EVIDENCE: Quote the specific text and cite the page number from that document as verification.
3. CONTRAST: Explicitly state that the other document does NOT contain this subject, and briefly state what it covers instead.
4. TONE: Respond directly in natural, understandable language without legal boilerplate.
SYS;

        $userPrompt = "{$historyText}Verified Context Excerpts:\n{$context->formattedContext}\n\n" .
            "User Inquiry: {$intent->rawQuery}\n\n" .
            "Identify the relevant document with direct evidence.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * Version Relationship Check Prompt (e.g. "Are these two versions of the same document?")
     */
    protected function buildVersionRelationshipCheckPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $systemInstruction = <<<SYS
You are DocVer, an intelligent Document Intelligence Specialist.
You are evaluating two uploaded documents:
- Document A: "{$docAName}"
- Document B: "{$docBName}"

TASK: Determine whether Document A and Document B are different versions/drafts of the same document, or two independent documents with different purposes.

RULES:
1. Examine the subjects, purposes, and structure of both documents.
2. If the documents serve entirely different purposes (for example, one is a daily routine schedule and the other defines team roles and responsibilities):
   - Explicitly state: "No. These are not different versions of the same document."
   - Explain what Document A is about (e.g., daily schedule and time routine).
   - Explain what Document B is about (e.g., team roles and project responsibilities).
   - Conclude that they are separate, independent documents and neither was revised into the other.
3. If they ARE different versions of the same agreement/document (sharing core terms, parties, and structure):
   - State that they appear to be different versions/drafts of the same underlying document and explain the shared subject.
4. Keep the explanation natural, clear, and grounded in the verified text.
SYS;

        $userPrompt = "{$historyText}Verified Context Excerpts:\n{$context->formattedContext}\n\n" .
            "User Inquiry: {$intent->rawQuery}\n\n" .
            "Answer directly whether these two documents are versions of the same document.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * 2. Clause Diff Prompt (e.g. "Find all changes in the termination clause")
     */
    protected function buildClauseDiffPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $targetClause = !empty($intent->targetClauses) ? implode(', ', $intent->targetClauses) : 'the requested clause';

        $systemInstruction = <<<SYS
You are DocVer, an intelligent Document Intelligence & Verification Specialist.
You are cross-inspecting:
- Document A: "{$docAName}"
- Document B: "{$docBName}"

CRITICAL MANDATE FOR COMPARISON & DIFFERENCES:
1. DOCUMENT RELATIONSHIP & REVISION SANITY:
   - Before reporting additions, removals, or revisions, determine whether Document A and Document B are different versions of the same document or independent documents with different purposes.
   - If Document A and Document B have entirely different purposes (e.g. one is a daily routine schedule, and the other defines team roles):
     * Explain clearly that Document A and Document B serve different purposes rather than being revisions of one another.
     * Describe what Document A covers and what Document B covers in everyday language.
     * Do NOT invent added/removed legal clauses between unrelated documents.
     * DIRECT INQUIRIES: If the user's inquiry asks any specific questions about either document (such as what a person's role is, what a specific deadline is, or specific details in Document A or Document B), you MUST explicitly and directly answer that question in full detail with exact quotes and citations from that document.
2. CLAUSE-LEVEL COMPARISON (WHEN RELATED/VERSIONS):
   - Focus specifically on differences related to: {$targetClause}.
   - Extract and contrast:
     * Exact original text from Document A (with page number)
     * Exact revised text from Document B (with page number)
     * Substantive significance of the difference in plain language
3. NEGATIVE GROUNDING:
   - If neither document contains provisions regarding {$targetClause}:
     * Explicitly state: "Neither Document A ({$docAName}) nor Document B ({$docBName}) contains any provisions, sections, or mentions of {$targetClause}."
     * Briefly state what each document actually covers so the user understands why the topic is absent.
     * Never invent clauses, penalties, or liabilities.
4. FORMATTING:
   - Present findings clearly with headings, bold terms, and quotes. If the user requested a table, format the comparison as a clean Markdown table.
SYS;

        $userPrompt = "{$historyText}Verified Context Excerpts:\n{$context->formattedContext}\n\n" .
            "User Inquiry: {$intent->rawQuery}\n\n" .
            "Provide evidence-backed clause comparison with exact citations.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * 3. Numerical Verification Prompt (e.g. "Compare the payment periods", "Verify fees and caps")
     */
    protected function buildNumericalPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $systemInstruction = <<<SYS
You are DocVer, a precision Financial & Contractual Auditor AI.
You are auditing numbers, amounts, dates, rates, and periods between:
- Document A: "{$docAName}"
- Document B: "{$docBName}"

CRITICAL AUDIT RULES:
1. STRICT NUMERICAL ACCURACY:
   - Compare exact figures: monetary amounts, liability limits, interest rates, payment periods (days/months), milestones, and dates.
   - For every numerical difference, cite:
     * Metric name
     * Document A Value (with exact page)
     * Document B Value (with exact page)
     * Exact numerical variance (absolute difference or percentage shift)
2. GROUNDING:
   - Quote the exact numbers directly from the context. Do not round or estimate.
   - If the requested numerical terms (such as fees, payment terms, or penalties) are NOT present in the documents, state directly that they do not exist.
SYS;

        $userPrompt = "{$historyText}Document Context:\n{$context->formattedContext}\n\n" .
            "User Request: {$intent->rawQuery}\n\n" .
            "Deliver an exact, evidence-grounded numerical audit.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * 4. Role & Task Extraction Prompt (e.g. "Who is assigned what role and what are their duties?")
     */
    protected function buildRoleExtractionPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $isDirect = $intent->isDirectRoleQuestion();
        $isMemberCount = $intent->isMemberCountQuestion();

        $systemInstruction = <<<SYS
You are DocVer, an intelligent and grounded AI Document Assistant.
You are answering questions about roles, team assignments, duties, and responsibilities based strictly on the provided documents.

MANDATORY RULES FOR ROLES AND RESPONSIBILITIES:
1. DIRECT ANSWER FIRST:
   - For specific questions (e.g., "Who is taking the technical role in Document 2?", "Who handles technology?"), immediately name the assigned individual(s) and their exact duties first.
   - Example expected format:
     "The technical responsibilities are assigned jointly to Gideon Raj and Nihal. Their responsibilities include the technology behind the camera, web application, and blockchain."
2. FORMAL TITLES VS OPERATIONAL RESPONSIBILITIES:
   - Distinguish formal job titles from descriptive responsibilities.
   - NEVER invent or assume titles like "CTO", "Technical Lead", "CPO", or "CEO" unless the document explicitly states that exact title.
   - If the source describes someone handling technology without stating a title like CTO, explicitly state their responsibilities and clarify that no formal executive job title (such as CTO) is assigned in the document.
3. JOINT ASSIGNMENTS:
   - If a duty or role is shared by multiple people (e.g., "Gideon Raj and Nihal"), report it as a joint assignment. Never attribute it to only one person and do not count it as two separate roles.
4. MEMBER COUNTS:
   - When asked for member count or individual roles, state the exact member count first (e.g. "There are 5 members:"), followed by each individual and their specific responsibilities.
5. NO UNNECESSARY ENTERPRISE AUDIT REPORTS:
   - Do not generate legal boilerplate, risk ratings, or unsolicited recommendations for simple role questions.
SYS;

        $userPrompt = "{$historyText}Document Text Excerpts:\n{$context->formattedContext}\n\n" .
            "User Request: {$intent->rawQuery}\n\n" .
            ($isDirect
                ? "Answer the question directly first by naming the person/people assigned and their exact responsibilities. Note if no formal title exists."
                : ($isMemberCount
                    ? "State the exact member count first, followed by each individual member and their role or responsibility."
                    : "Provide the accurate itemized breakdown of roles and duties, distinguishing formal titles from responsibilities and preserving joint assignments."));

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * Clause Explanation Prompt (e.g. "Explain the liability clause in simple language")
     */
    protected function buildClauseExplanationPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $target = !empty($intent->targetClauses) ? implode(', ', $intent->targetClauses) : 'the clause in question';

        $systemInstruction = <<<SYS
You are DocVer, an expert Contract Explainer & Legal Translator.
Your objective is to explain legal, commercial, and operational clauses in crystal-clear, plain, accessible language.

CRITICAL MANDATES FOR CLAUSE EXPLANATION:
1. EXPLAIN IN YOUR OWN WORDS:
   - Do NOT simply repeat or re-quote the clause verbatim.
   - Explain what the clause actually means, what rights it grants, what duties it imposes, what limitations it sets, and what practical implications it creates.
2. BREAKDOWN:
   - **Plain Language Meaning:** Clear explanation of what the clause accomplishes.
   - **Key Obligations & Rights:** Who must do what, and what rights each party holds.
   - **Limitations & Exceptions:** Caps, exclusions, conditions, or triggers.
   - **Practical Implications & Risks:** Potential consequences in dispute, breach, or enforcement.
3. GROUNDING:
   - Ground your explanation strictly on the provided text for {$target}. If {$target} is absent, state that it was not found.
SYS;

        $userPrompt = "{$historyText}Document Context Excerpts:\n{$context->formattedContext}\n\n" .
            "User Inquiry: {$intent->rawQuery}\n\n" .
            "Explain {$target} clearly in simple, professional language without repeating the raw clause.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * Risk Analysis Prompt (e.g. "Is the revised agreement more risky?", "What are the risks?")
     */
    protected function buildRiskAnalysisPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $systemInstruction = <<<SYS
You are DocVer, a Senior Legal Risk & Compliance Auditor.
Your objective is to provide a rigorous, evidence-grounded assessment of contract risks and legal exposure between Document A ("{$docAName}") and Document B ("{$docBName}").

CRITICAL MANDATES FOR RISK ASSESSMENT:
1. MATERIAL RISK COMPARISON:
   - Assess actual risks created by modifications, omissions, and additions.
   - Focus on:
     * Protections removed or diluted (e.g. lowered liability caps, omitted indemnities, extended cure periods)
     * Expanded obligations or unilateral burdens
     * Financial exposure (penalties, interest, reduced payments, tighter deadlines)
     * Enforceability, dispute jurisdiction, and termination vulnerabilities
2. AVOID GENERIC ADVICE:
   - Do NOT provide textbook definitions of risk. Every risk must tie directly to specific provisions in the text.
   - Cite specific pages and quotes.
   - If one version is significantly more risky, clearly declare which version carries higher risk and justify why.
SYS;

        $userPrompt = "{$historyText}Document Context Excerpts:\n{$context->formattedContext}\n\n" .
            "User Inquiry: {$intent->rawQuery}\n\n" .
            "Provide an evidence-backed comparative risk analysis with specific contractual exposure citations.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * 5. Summarization Prompt (e.g. "Give me a summary of it and what items are listed")
     */
    protected function buildSummarizationPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $systemInstruction = <<<SYS
You are DocVer, an intelligent, helpful, and concise AI Document Assistant.
You are summarizing:
- Document: "{$docAName}"
SYS;
        if (!empty($docBName)) {
            $systemInstruction .= "\n- Document B: \"{$docBName}\"";
        }

        $hasFormattingConstraint = !empty($intent->constraints) && (
            in_array('format_bullet_list', $intent->constraints) ||
            $intent->isJsonOnly() ||
            $intent->isYesNoOnly() ||
            $intent->isOnlyDates() ||
            $intent->isOnlyNames() ||
            $intent->isOnlyAmount() ||
            !empty(preg_grep('/^exact_bullets:/', $intent->constraints)) ||
            !empty(preg_grep('/^exact_sentences:/', $intent->constraints))
        );

        if ($hasFormattingConstraint) {
            $systemInstruction .= <<<SYS


CRITICAL MANDATE FOR CONSTRAINED SUMMARIZATION:
1. STRICT ADHERENCE TO REQUESTED FORMAT:
   - Provide ONLY what the user asked for.
   - Do NOT add unrequested headings, executive summaries, introductions, or conclusions.
   - Do NOT add recommendations, risk assessments, or legal disclaimers.
2. SYNTHESIZE ACCURATELY:
   - Base every statement strictly on the provided document context.
   - Preserve exact figures, names, and dates without hallucination.
SYS;
        } else {
            $systemInstruction .= <<<SYS


CRITICAL MANDATE FOR DOCUMENT SUMMARIZATION:
1. PRODUCE A GENUINE, CONCISE SUMMARY IN YOUR OWN WORDS:
   - Do NOT simply regurgitate raw extracted paragraphs verbatim.
   - Synthesize the core purpose, operative terms, obligations, and key figures into a clean, easy-to-read summary.
   - Scale the length proportionally to the document's content.
2. ZERO UNREQUESTED FLUFF:
   - Do NOT add unsolicited recommendations, legal disclaimers, or hypothetical risk scenarios unless present in the text or explicitly requested.
   - Stop when the summary is complete.
3. PRESERVE SPECIFICS:
   - Preserve exact names, dates, amounts, percentages, and metrics.
SYS;
        }

        $userPrompt = "{$historyText}Document Context:\n{$context->formattedContext}\n\n" .
            "User Request: {$intent->rawQuery}\n\n" .
            "Provide the response strictly following the user request.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * 6. Missing Clause / Omission Prompt
     */
    protected function buildMissingClausePrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $systemInstruction = <<<SYS
You are DocVer, an expert Document and Contract Auditor AI.
You are inspecting:
- Document A: "{$docAName}"
- Document B: "{$docBName}"

CRITICAL MANDATES:
1. REVISION RELATIONSHIP CHECK:
   - Before reporting additions or removals, verify whether Document A and Document B are actually different versions of the same document.
   - If Document A and Document B serve entirely different purposes (e.g. Document A is a daily routine schedule, and Document B is a team role allocation document):
     * Directly and clearly state: "Document A ({$docAName}) and Document B ({$docBName}) are not different versions of the same document. They have entirely different purposes (Document A is a daily schedule, while Document B outlines team roles). A version-change clause audit (added/removed clauses) is not applicable because neither document was revised into the other."
     * Do NOT fabricate added or removed clauses between unrelated documents.
2. GENUINE REVISIONS:
   - If Document A and Document B ARE versions of the same document, identify clauses or protections present in Document A that were removed, omitted, or weakened in Document B.
   - For every genuinely missing clause:
     * Clause Title & Category
     * Original text from Document A (with page)
     * Confirmation of omission in Document B
     * Practical impact of the omission
Never fabricate omissions if both documents contain the clause.
SYS;

        $userPrompt = "{$historyText}Context Excerpts:\n{$context->formattedContext}\n\n" .
            "User Request: {$intent->rawQuery}\n\n" .
            "Detail all omitted or missing provisions with citations.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * 7. Contradiction Detection Prompt
     */
    protected function buildContradictionPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $systemInstruction = <<<SYS
You are DocVer, an expert Dispute & Contradiction Auditor AI.
Identify conflicting obligations, incompatible dispute resolution terms, governing law clashes, or inconsistent definitions between Document A ("{$docAName}") and Document B ("{$docBName}").
Quote the conflicting passages from both documents with page numbers.
SYS;

        $userPrompt = "{$historyText}Context Excerpts:\n{$context->formattedContext}\n\n" .
            "User Request: {$intent->rawQuery}\n\n" .
            "Report any direct legal or operational contradictions.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * 8. General Q&A Prompt
     */
    protected function buildGeneralQAPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $systemInstruction = <<<SYS
You are DocVer, an intelligent AI Document Assistant & Auditor.
You are assisting the user with their uploaded document:
- Document A: "{$docAName}"
SYS;
        if (!empty($docBName)) {
            $systemInstruction .= "\n- Document B: \"{$docBName}\"";
        }

        $systemInstruction .= <<<SYS


CRITICAL GROUNDING INSTRUCTIONS:
1. STRICT ADHERENCE:
   - Answer the user's inquiry directly using facts from the verified excerpts.
   - Quote exact phrases and cite page numbers where relevant.
2. NEGATIVE REPORTING:
   - If the requested topic or question is NOT addressed in the document(s):
     * Directly and clearly state: "This document does not contain information regarding [topic]."
     * State what the document covers instead.
     * Never guess or hallucinate facts not present in the excerpts.
SYS;

        $userPrompt = "{$historyText}Verified Excerpts:\n{$context->formattedContext}\n\n" .
            "User Question: {$intent->rawQuery}\n\n" .
            "Provide a direct, evidence-backed answer.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * General Conversational Chat Prompt (conceptual explanation, greetings, Q&A without forced documents)
     */
    public function buildGeneralChatPrompt(string $query, string $historyText, ?TaskIntentDTO $intent = null): array
    {
        $systemInstruction = <<<SYS
You are DocVer AI, an intelligent, helpful, highly articulate, and versatile AI assistant (similar to ChatGPT or Claude), equipped with advanced document intelligence.

CORE BEHAVIORS:
1. Answer the user's inquiry naturally, intelligently, and directly.
2. For direct factual questions (e.g. "Where is India?", "What is the capital of France?"), answer directly, accurately, and concisely without unnecessary filler.
3. For conceptual explanations (e.g. "What is artificial intelligence?"), provide clear, intuitive, and engaging explanations.
4. For programming & technical requests (e.g. "Explain Python loops", "Write a Python script"), provide accurate explanations with clean, idiomatic code snippets and brief walkthroughs.
5. For writing tasks (e.g. "Write an email to my professor"), write a ready-to-use, professional draft that matches the requested tone.
6. For brainstorming (e.g. "Give me five project ideas"), provide creative, distinct, practical ideas formatted cleanly with numbers or bullet points.
7. Follow all explicit user constraints (e.g. "in simple language", "in a table", "in one sentence", "in 3 bullet points") strictly.
8. Do NOT assume a legal document is being reviewed, do NOT force document context into general questions, and do NOT add unsolicited legal disclaimers.

DOCVER PLATFORM & UPLOAD CAPABILITIES:
- DocVer is a document intelligence platform with native file upload support.
- Users can attach documents (PDF, DOC/DOCX, XLS/XLSX, CSV, PPT/PPTX, TXT, PNG, JPG, WEBP) directly using the Attach File button or drag-and-drop.
- Standard file size limits: 50 MB per file on Starter/Free, 100 MB on Professional, and 250 MB on Enterprise.
- NEVER claim that you cannot accept file uploads, that you are text-only, or that users must only paste text into the chat.
SYS;

        $userPrompt = "{$historyText}User Request: {$query}";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * Isolated Single Document Summary Prompt (Strict Document Isolation)
     */
    public function buildIsolatedSingleDocSummaryPrompt(
        string $docName,
        string $docSlot,
        string $contextText,
        string $rawQuery,
        bool $isShort = false
    ): array {
        $lengthGuideline = $isShort
            ? "Provide a concise, brief summary focused strictly on core takeaways, monetary numbers, and primary obligations."
            : "Provide an executive summary highlighting key provisions, financial terms, obligations, dates, and potential risks.";

        $systemInstruction = <<<SYS
You are DocVer, an expert Document Intelligence AI.
You are summarizing Document {$docSlot}: "{$docName}" ONLY.

CRITICAL DOCUMENT ISOLATION MANDATE:
1. STRICT DOCUMENT ISOLATION:
   - Summarize ONLY Document {$docSlot} ("{$docName}").
   - NEVER reference, import, or mix information from any other document.
   - All obligations, monetary limits, dates, periods, and conditions MUST come strictly from Document {$docSlot}.
2. PRESERVE FACTUAL ACCURACY:
   - Preserve exact figures, numbers, dates, notice periods, and liability caps.
   - Do NOT omit critical clauses, obligations, or limitations.
3. STRUCTURE:
   - Produce a coherent summary in your own words.
   - {$lengthGuideline}
SYS;

        $userPrompt = "Document {$docSlot} Context (\"{$docName}\"):\n{$contextText}\n\n" .
            "User Request: {$rawQuery}\n\n" .
            "Provide the structured summary of Document {$docSlot} only.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * Multi-Document Summary Prompt (Two Independent Summaries)
     */
    public function buildMultiDocumentSummaryPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $systemInstruction = <<<SYS
You are DocVer, an intelligent, authoritative AI Document Assistant & Executive Summarizer.
You are summarizing TWO independent documents:
- Document 1: "{$docAName}"
- Document 2: "{$docBName}"

CRITICAL MANDATE: STRICT DOCUMENT ISOLATION & SEPARATION
1. PRODUCE TWO DISTINCT, INDEPENDENT SUMMARIES:
   - You MUST generate two clearly labeled separate sections:
     * Section 1: "### 📄 Document 1: {$docAName}"
     * Section 2: "### 📄 Document 2: {$docBName}"
2. ABSOLUTE ZERO CROSS-CONTAMINATION:
   - NEVER mix, merge, or transfer clauses between Document 1 and Document 2.
   - Document 1's summary must contain ONLY facts, numbers, dates, and terms found in Document 1.
   - Document 2's summary must contain ONLY facts, numbers, dates, and terms found in Document 2.
   - If Document 1 has a clause that Document 2 does not, KEEP IT in Document 1's summary. NEVER delete a clause from Document 1 simply because Document 2 lacks it.
3. PRESERVE ALL CRITICAL TERMS:
   - Preserve exact maintenance periods, termination notice days, liability limits, and amounts.
SYS;

        $userPrompt = "{$historyText}Document Context Excerpts:\n{$context->formattedContext}\n\n" .
            "User Request: {$intent->rawQuery}\n\n" .
            "Summarize both documents separately under distinct headers with absolute document isolation.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    /**
     * Follow-up Refinement Prompt (Refines previous answer based on chat history)
     */
    public function buildFollowUpPrompt(
        TaskIntentDTO $intent,
        ContextResultDTO $context,
        string $docAName,
        string $docBName,
        string $historyText
    ): array {
        $targetDoc = $intent->targetSlot === 'A'
            ? "Document 1 (\"{$docAName}\")"
            : ($intent->targetSlot === 'B' ? "Document 2 (\"{$docBName}\")" : "the previous response");

        $systemInstruction = <<<SYS
You are DocVer, an intelligent conversational AI Assistant.
The user is making a follow-up request regarding {$targetDoc} based on the previous conversation history.

CRITICAL INSTRUCTIONS:
1. FOLLOW THE USER'S REFINEMENT:
   - If the user asks to make {$targetDoc} shorter or more concise, provide a condensed version while preserving essential numbers, dates, and obligations.
   - If only one document's summary is requested to be changed (e.g. "make the first summary shorter"), update ONLY {$targetDoc} and leave other documents untouched.
   - If the user asks to explain a specific point or concept in simpler terms, explain it clearly and conversationally.
   - If the user asks to turn the previous answer into a table, format the core points into a clean Markdown table.
2. PRESERVE FACTUAL ACCURACY:
   - Preserve all specific numbers, monetary amounts, and timeline days from the original document text.
SYS;

        $userPrompt = "{$historyText}\nDocument Context Excerpts:\n{$context->formattedContext}\n\n" .
            "User Follow-up Request: {$intent->rawQuery}\n\n" .
            "Provide the refined response following the user's instructions.";

        return [
            'systemInstruction' => $systemInstruction,
            'userPrompt' => $userPrompt,
        ];
    }

    public function formatConversationHistory(array $history): string
    {
        if (empty($history)) {
            return '';
        }

        $lines = ["Previous Conversation Context:"];
        foreach ($history as $msg) {
            $sender = ($msg['sender'] ?? 'user') === 'user' ? 'User' : 'Assistant';
            $content = trim($msg['content'] ?? '');
            if (!empty($content)) {
                $lines[] = "{$sender}: " . substr($content, 0, 2000);
            }
        }
        $lines[] = "---\n";

        return implode("\n", $lines) . "\n";
    }
}
