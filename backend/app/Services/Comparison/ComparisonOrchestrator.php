<?php

namespace App\Services\Comparison;

use App\Models\ComparisonJob;
use App\Models\ComparisonReport;
use App\Services\Comparison\DTOs\CandidateFindingDTO;
use App\Services\Comparison\DTOs\ComparisonResultDTO;
use App\Services\Comparison\DTOs\DocumentRelationshipDTO;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use App\Services\Documents\Pipeline\PipelineProgressTracker;
use Illuminate\Support\Facades\Log;

class ComparisonOrchestrator
{
    public function __construct(
        protected EphemeralIndexManager $indexManager,
        protected PipelineProgressTracker $progressTracker,
        protected DocumentStructureAnalyzer $structureAnalyzer,
        protected SectionMatcher $sectionMatcher,
        protected MissingSectionDetector $missingDetector,
        protected ClauseComparisonService $clauseComparisonService,
        protected EvidenceValidator $evidenceValidator,
        protected ReportGenerator $reportGenerator,
        protected ?DocumentRelationshipClassifier $relationshipClassifier = null
    ) {
        $this->relationshipClassifier = $relationshipClassifier ?? app(DocumentRelationshipClassifier::class);
    }

    /**
     * Execute full intelligent comparison workflow between Document A and Document B.
     */
    public function compare(ComparisonJob $job): ComparisonResultDTO
    {
        $jobId = $job->id;
        Log::info("Starting ComparisonOrchestrator for Job #{$jobId}");

        // 1. Retrieve chunks from ephemeral storage
        $chunksA = $this->indexManager->getChunks($jobId, 'A');
        $chunksB = $this->indexManager->getChunks($jobId, 'B');

        if (empty($chunksA) || empty($chunksB)) {
            throw new \RuntimeException("Indexed chunks missing for Job #{$jobId}. Processing cannot proceed.");
        }

        // Apply AI Focus Prompt (page filtering or topic scoping) if requested
        $focusPrompt = trim($job->metadata['focus_prompt'] ?? '');
        if (! empty($focusPrompt)) {
            Log::info("Job #{$jobId} executing with custom AI focus prompt: '{$focusPrompt}'");

            // Page scoping (e.g. "page 2", "pages 1-2", "p. 2")
            if (preg_match('/(?:page|pages|p\.?)\s*([0-9]+)(?:\s*(?:to|-|and|,)\s*([0-9]+))?/i', $focusPrompt, $pageMatches)) {
                $startPage = (int) $pageMatches[1];
                $endPage = ! empty($pageMatches[2]) ? (int) $pageMatches[2] : $startPage;
                $filteredA = array_values(array_filter($chunksA, fn ($c) => ($c['page_number'] ?? 1) >= $startPage && ($c['page_number'] ?? 1) <= $endPage));
                $filteredB = array_values(array_filter($chunksB, fn ($c) => ($c['page_number'] ?? 1) >= $startPage && ($c['page_number'] ?? 1) <= $endPage));
                if (! empty($filteredA) && ! empty($filteredB)) {
                    $chunksA = $filteredA;
                    $chunksB = $filteredB;
                    Log::info("Job #{$jobId} chunks scoped to pages {$startPage}-{$endPage} based on focus prompt");
                }
            }
        }

        // Check if resuming from previous stage (if already completed, re-run from scratch)
        $lastStage = $job->last_completed_stage === 'completed' ? null : $job->last_completed_stage;
        $matches = [];
        $unmatchedA = [];
        $unmatchedB = [];
        $candidateFindings = [];

        // 2. Stage: matching_sections (60%)
        if ($lastStage === null || in_array($lastStage, ['validating', 'chunking', 'indexing'])) {
            $this->progressTracker->updateProgress($jobId, 'matching_sections', 60);

            $sectionsA = $this->structureAnalyzer->analyzeStructure($chunksA, 'A');
            $sectionsB = $this->structureAnalyzer->analyzeStructure($chunksB, 'B');

            $matchResult = $this->sectionMatcher->matchSections($sectionsA, $sectionsB);
            $matches = $matchResult['matches'];
            $unmatchedA = $matchResult['unmatchedA'];
            $unmatchedB = $matchResult['unmatchedB'];

            $docAName = $job->documentA?->original_name ?? 'Document A';
            $docBName = $job->documentB?->original_name ?? 'Document B';
            $relationship = $this->relationshipClassifier->classify($sectionsA, $sectionsB, $docAName, $docBName, $focusPrompt);

            // Save intermediate matching state & document relationship
            $allChunkIds = array_merge(
                array_column($chunksA, 'id'),
                array_column($chunksB, 'id')
            );
            $existingMeta = $job->metadata ?? [];
            $existingMeta['comparison_mode'] = $relationship->recommendedMode;
            $existingMeta['document_relationship'] = $relationship->relationship;
            $existingMeta['relationship_summary'] = $relationship->relationshipSummary;
            $existingMeta['purpose_a'] = $relationship->purposeA;
            $existingMeta['purpose_b'] = $relationship->purposeB;

            $job->update([
                'last_completed_stage' => 'matching_sections',
                'completed_chunks' => $allChunkIds,
                'metadata' => $existingMeta,
            ]);
        } else {
            Log::info("Resuming Job #{$jobId} past matching_sections stage (last: {$lastStage})");
            // Regenerate lightweight section matches from cached structure if needed
            $sectionsA = $this->structureAnalyzer->analyzeStructure($chunksA, 'A');
            $sectionsB = $this->structureAnalyzer->analyzeStructure($chunksB, 'B');
            $matchResult = $this->sectionMatcher->matchSections($sectionsA, $sectionsB);
            $matches = $matchResult['matches'];
            $unmatchedA = $matchResult['unmatchedA'];
            $unmatchedB = $matchResult['unmatchedB'];

            $docAName = $job->documentA?->original_name ?? 'Document A';
            $docBName = $job->documentB?->original_name ?? 'Document B';
            $relationship = $this->relationshipClassifier->classify($sectionsA, $sectionsB, $docAName, $docBName, $focusPrompt);
        }

        // 3. Stage: comparing (75%)
        if ($lastStage === null || in_array($lastStage, ['matching_sections', 'validating', 'chunking', 'indexing'])) {
            $this->progressTracker->updateProgress($jobId, 'comparing', 75);

            if ($relationship->isVersionRevision()) {
                // True document revision comparison (contracts/agreements/revisions)
                $missingFindings = $this->missingDetector->detectMissingAndAdded($unmatchedA, $unmatchedB);
                $clauseFindings = $this->clauseComparisonService->compareMatches($matches);
                $rawFindings = array_merge($missingFindings, $clauseFindings);
            } else {
                // General document comparison / different purposes (e.g. Schedule vs Roles)
                // Avoids false clause removals/additions and legal risk boilerplate
                $rawFindings = $this->buildGeneralComparisonFindings($relationship, $sectionsA, $sectionsB, $docAName, $docBName);
            }

            // If a topic or clause focus prompt was specified, filter or prioritize matching findings
            if (! empty($focusPrompt)) {
                $keywords = array_filter(
                    preg_split('/[\s,;:?.\-]+/', strtolower($focusPrompt)),
                    fn ($w) => strlen($w) > 3 && ! in_array($w, ['only', 'focus', 'test', 'compare', 'check', 'clause', 'clauses', 'section', 'about', 'with', 'from', 'that', 'this', 'page', 'pages', 'what', 'between', 'these', 'documents', 'document', 'difference', 'differences', 'show', 'tell', 'give', 'summary', 'list'])
                );

                if (! empty($keywords)) {
                    $topicFindings = array_filter($rawFindings, function ($f) use ($keywords) {
                        $text = strtolower("{$f->title} {$f->category} {$f->explanation} {$f->docAEvidence} {$f->docBEvidence}");
                        foreach ($keywords as $kw) {
                            if (str_contains($text, $kw)) {
                                return true;
                            }
                        }
                        return false;
                    });

                    if (! empty($topicFindings)) {
                        $rawFindings = array_values($topicFindings);
                        Log::info("Job #{$jobId} filtered to " . count($rawFindings) . " findings matching topic focus '{$focusPrompt}'");
                    } else {
                        // User specifically requested to focus on a topic not present in the documents.
                        // Strictly avoid cross-topic fallback or unrelated findings.
                        $rawFindings = [];
                        Log::info("Job #{$jobId}: No findings matched requested topic focus '{$focusPrompt}'. Cleared unrelated findings.");
                    }
                }
            }

            // Apply idempotency keys to prevent duplicates
            $deduped = [];
            foreach ($rawFindings as $f) {
                $idempotencyKey = hash('sha256', "{$jobId}_{$f->type}_{$f->title}_{$f->docAPage}_{$f->docBPage}");
                if (! isset($deduped[$idempotencyKey])) {
                    $deduped[$idempotencyKey] = $f;
                }
            }
            $candidateFindings = array_values($deduped);

            $job->update([
                'last_completed_stage' => 'comparing',
            ]);
        }

        // 4. Stage: validating_evidence (90%)
        $this->progressTracker->updateProgress($jobId, 'validating_evidence', 90);

        $validatedFindings = $this->evidenceValidator->validateFindings(
            candidateFindings: $candidateFindings,
            chunksA: $chunksA,
            chunksB: $chunksB
        );

        $job->update([
            'last_completed_stage' => 'validating_evidence',
        ]);

        // 5. Stage: generating_report (95%)
        $this->progressTracker->updateProgress($jobId, 'generating_report', 95);

        $result = $this->reportGenerator->generateAndPersist($job, $validatedFindings, $relationship);

        // Update Job with similarity score
        $job->update([
            'similarity_score' => $result->similarityScore,
            'last_completed_stage' => 'completed',
            'completed_at' => now(),
        ]);

        // 6. Stage: completed (100%)
        $this->progressTracker->updateProgress($jobId, 'completed', 100, [
            'similarity_score' => $result->similarityScore,
            'total_differences' => $result->summary['total_differences'],
        ]);

        Log::info("ComparisonOrchestrator completed for Job #{$jobId} (Similarity: {$result->similarityScore}%)");

        return $result;
    }

    protected function buildGeneralComparisonFindings(
        DocumentRelationshipDTO $relationship,
        array $sectionsA,
        array $sectionsB,
        string $docAName,
        string $docBName
    ): array {
        $findings = [];

        // 1. Primary finding: Overall document purpose comparison
        $findings[] = new CandidateFindingDTO(
            id: "diff_purpose_" . uniqid(),
            type: 'not_comparable',
            category: 'Content & Purpose',
            severity: 'none',
            title: "Different Purposes: " . ucfirst(str_replace('_', ' ', $relationship->typeA)) . " vs " . ucfirst(str_replace('_', ' ', $relationship->typeB)),
            docAPage: 1,
            docAEvidence: !empty($sectionsA) ? $this->createExcerpt($sectionsA[0]->content, 40) : "Document A: {$docAName}",
            docBPage: 1,
            docBEvidence: !empty($sectionsB) ? $this->createExcerpt($sectionsB[0]->content, 40) : "Document B: {$docBName}",
            explanation: $relationship->relationshipSummary,
            confidence: 1.0,
            metrics: [
                'impact' => 'Documents serve different operational functions.',
                'verification_status' => 'evidence_validated',
                'comparison_type' => 'not_comparable',
            ],
            verificationStatus: 'evidence_validated'
        );

        // 2. Add distinct topic findings for unique sections in Doc A
        foreach (array_slice($sectionsA, 0, 3) as $secA) {
            $findings[] = new CandidateFindingDTO(
                id: "topic_a_" . uniqid(),
                type: 'unique_a',
                category: $secA->category,
                severity: 'none',
                title: "Unique to Document A ({$docAName}): {$secA->title}",
                docAPage: $secA->pageStart,
                docAEvidence: $this->createExcerpt($secA->content, 40),
                docBPage: null,
                docBEvidence: null,
                explanation: "Document A contains '{$secA->title}' focusing on {$relationship->purposeA}. Document B does not contain a corresponding section because it has a different purpose ({$relationship->purposeB}).",
                confidence: 0.99,
                metrics: [
                    'impact' => "Specific to {$docAName}.",
                    'verification_status' => 'evidence_validated',
                    'comparison_type' => 'unique_to_a',
                ],
                verificationStatus: 'evidence_validated'
            );
        }

        // 3. Add distinct topic findings for unique sections in Doc B
        foreach (array_slice($sectionsB, 0, 3) as $secB) {
            $findings[] = new CandidateFindingDTO(
                id: "topic_b_" . uniqid(),
                type: 'unique_b',
                category: $secB->category,
                severity: 'none',
                title: "Unique to Document B ({$docBName}): {$secB->title}",
                docAPage: null,
                docAEvidence: null,
                docBPage: $secB->pageStart,
                docBEvidence: $this->createExcerpt($secB->content, 40),
                explanation: "Document B includes '{$secB->title}' outlining specific operational information. Document A does not contain a corresponding section because it focuses on {$relationship->purposeA}.",
                confidence: 0.99,
                metrics: [
                    'impact' => "Specific to {$docBName}.",
                    'verification_status' => 'evidence_validated',
                    'comparison_type' => 'unique_to_b',
                ],
                verificationStatus: 'evidence_validated'
            );
        }

        return $findings;
    }

    protected function createExcerpt(string $content, int $maxWords = 40): string
    {
        $words = preg_split('/\s+/', trim($content), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) <= $maxWords) {
            return implode(' ', $words);
        }

        return implode(' ', array_slice($words, 0, $maxWords)) . ' ...';
    }
}
