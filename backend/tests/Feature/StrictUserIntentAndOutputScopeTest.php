<?php

namespace Tests\Feature;

use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\AIOrchestrator;
use App\Services\AI\Intent\TaskIntentClassifier;
use App\Services\AI\Validation\ResponseValidator;
use App\Services\AI\Context\ContextResultDTO;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrictUserIntentAndOutputScopeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected TaskIntentClassifier $classifier;
    protected ResponseValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::create([
            'name' => 'Strict Intent Test Workspace',
            'slug' => 'intent-ws',
            'owner_id' => $this->user->id,
            'plan' => 'enterprise',
        ]);
        $this->user->workspaces()->attach($this->workspace->id, ['role' => 'owner']);

        $this->classifier = app(TaskIntentClassifier::class);
        $this->validator = app(ResponseValidator::class);
    }

    /**
     * TEST 1: "What is the capital of India?"
     * Direct factual question -> general_chat with direct_factual_answer.
     * No unrequested legal contracts, disclaimers, or recommendations.
     */
    public function test_prompt_01_factual_question_direct_answer(): void
    {
        $prompt = "What is the capital of India?";
        $intent = $this->classifier->classify($prompt);

        $this->assertEquals('general_chat', $intent->intent);
        $this->assertContains('direct_factual_answer', $intent->constraints);

        $sampleOutput = "Certainly! The capital of India is New Delhi. Let me know if you need anything else!";
        $cleaned = $this->validator->applyConstraintPostProcessing($sampleOutput, $intent);

        $this->assertStringContainsString('New Delhi', $cleaned);
        $this->assertStringNotContainsString('Certainly!', $cleaned);
        $this->assertStringNotContainsString('Let me know if', $cleaned);
    }

    /**
     * TEST 2: "Extract only the names from this document."
     * Output returns only names, no background explanations or roles.
     */
    public function test_prompt_02_extract_only_names(): void
    {
        $prompt = "Extract only the names from this document.";
        $intent = $this->classifier->classify($prompt);

        $this->assertTrue($intent->isOnlyNames());
        $this->assertContains('strict_extraction_only', $intent->constraints);

        $verboseOutput = "Here are the names found in the document:\n- Nihal\n- Gideon Raj\n- Abhirup Kumar\n- Jayesh\n\nRoles:\nNihal is product lead.\nRecommendations:\nAssign more members.";
        $cleaned = $this->validator->applyConstraintPostProcessing($verboseOutput, $intent);

        $this->assertStringContainsString('Nihal', $cleaned);
        $this->assertStringContainsString('Gideon Raj', $cleaned);
        $this->assertStringNotContainsString('Recommendations:', $cleaned);
        $this->assertStringNotContainsString('Assign more members', $cleaned);
    }

    /**
     * TEST 3: "Summarize this document in 3 bullet points."
     * Enforce exactly 3 bullet points, zero unrequested 9-section headings.
     */
    public function test_prompt_03_summarize_in_exact_3_bullet_points(): void
    {
        $prompt = "Summarize this document in 3 bullet points.";
        $intent = $this->classifier->classify($prompt);

        $this->assertContains('exact_bullets:3', $intent->constraints);

        $rawLlmOutput = "### Executive Summary\n- First key point about document.\n- Second key point regarding schedule.\n- Third key point on obligations.\n- Fourth unwanted point.\n- Fifth point.\n\n### Conclusion\nDone.";
        $cleaned = $this->validator->applyConstraintPostProcessing($rawLlmOutput, $intent);

        $bulletCount = substr_count($cleaned, '- ');
        $this->assertEquals(3, $bulletCount, "Must contain exactly 3 bullet points");
        $this->assertStringNotContainsString('### Conclusion', $cleaned);
    }

    /**
     * TEST 4: "Give me the difference between these two documents."
     * Core task is clause diff, no artificial 8 discrepancy cards.
     */
    public function test_prompt_04_difference_between_two_documents(): void
    {
        $prompt = "Give me the difference between these two documents.";
        $intent = $this->classifier->classify($prompt);

        $this->assertEquals('clause_diff', $intent->intent);
        $this->assertEquals('both', $intent->targetSlot);
        $this->assertEquals('cross_document', $intent->scope);
    }

    /**
     * TEST 5: "Only show the numerical differences."
     * Enforce only numerical differences (rates, dates, amounts, numbers).
     */
    public function test_prompt_05_only_numerical_differences(): void
    {
        $prompt = "Only show the numerical differences.";
        $intent = $this->classifier->classify($prompt);

        $this->assertTrue($intent->isOnlyNumerical());
        $this->assertContains('only_differences', $intent->constraints);

        $verboseDiff = "- Document A specifies fee of $5,000 whereas Document B specifies $7,500.\n- Document A requires 30 days notice; Document B requires 60 days.\n- Document A contains a governing law clause in Delaware.\n- Document B has non-compete terms.";
        $cleaned = $this->validator->applyConstraintPostProcessing($verboseDiff, $intent);

        $this->assertStringContainsString('$5,000', $cleaned);
        $this->assertStringContainsString('30 days', $cleaned);
        $this->assertStringNotContainsString('governing law', $cleaned);
    }

    /**
     * TEST 6: "Explain clause 4 in simple language."
     * Target section 4, simple language, no analyzing other clauses.
     */
    public function test_prompt_06_explain_clause_in_simple_language(): void
    {
        $prompt = "Explain clause 4 in simple language.";
        $intent = $this->classifier->classify($prompt);

        $this->assertEquals('clause_explanation', $intent->intent);
        $this->assertContains('4', $intent->targetSections);
        $this->assertContains('simple_language', $intent->constraints);
    }

    /**
     * TEST 7: "Give me only the final amount."
     * Returns monetary figure only.
     */
    public function test_prompt_07_give_me_only_the_final_amount(): void
    {
        $prompt = "Give me only the final amount.";
        $intent = $this->classifier->classify($prompt);

        $this->assertTrue($intent->isOnlyAmount());
        $this->assertContains('strict_extraction_only', $intent->constraints);

        $rawResponse = "Based on section 5, the total final payable amount under this agreement is $125,000 USD after all discounts.";
        $cleaned = $this->validator->applyConstraintPostProcessing($rawResponse, $intent);

        $this->assertEquals('$125,000', $cleaned);
    }

    /**
     * TEST 8: "Make the previous response shorter."
     * Shortens response while preserving core facts.
     */
    public function test_prompt_08_make_previous_response_shorter(): void
    {
        $prompt = "Make the previous response shorter.";
        $intent = $this->classifier->classify($prompt);

        $this->assertEquals('follow_up_refinement', $intent->intent);
        $this->assertContains('make_shorter', $intent->constraints);

        $longText = "Sentence one outlines the main contract scope. Sentence two explains the payment schedule of thirty days. Sentence three provides the termination conditions. Sentence four elaborates on intellectual property. Sentence five provides supplementary context. Sentence six summarizes the liabilities.";
        $cleaned = $this->validator->applyConstraintPostProcessing($longText, $intent);

        $sentences = preg_split('/(?<=[.!?])\s+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY);
        $this->assertLessThanOrEqual(3, count($sentences), "Output must be condensed to 3 sentences or fewer");
    }

    /**
     * TEST 9: "Give me the answer in one sentence."
     * Output must be exactly one sentence.
     */
    public function test_prompt_09_give_answer_in_one_sentence(): void
    {
        $prompt = "Give me the answer in one sentence.";
        $intent = $this->classifier->classify($prompt);

        $this->assertContains('exact_sentences:1', $intent->constraints);

        $multiSentence = "Document A terminates on December 31. Document B extends through June 30. There are no penalty clauses.";
        $cleaned = $this->validator->applyConstraintPostProcessing($multiSentence, $intent);

        $sentences = preg_split('/(?<=[.!?])\s+/', trim($cleaned), -1, PREG_SPLIT_NO_EMPTY);
        $this->assertEquals(1, count($sentences), "Output must be exactly 1 sentence");
    }

    /**
     * TEST 10: "Do not give recommendations."
     * Zero recommendation sections or advice.
     */
    public function test_prompt_10_forbid_recommendations(): void
    {
        $prompt = "Do not give recommendations.";
        $intent = $this->classifier->classify($prompt);

        $this->assertTrue($intent->forbidsRecommendations());

        $contentWithRecs = "### Analysis\nThe liability cap is $50,000.\n\n### Recommendations\n1. Negotiate the cap to $100,000.\n2. Consult legal counsel.";
        $cleaned = $this->validator->applyConstraintPostProcessing($contentWithRecs, $intent);

        $this->assertStringContainsString('The liability cap is $50,000', $cleaned);
        $this->assertStringNotContainsString('Recommendations', $cleaned);
        $this->assertStringNotContainsString('Negotiate the cap', $cleaned);
    }

    /**
     * TEST 11: "Do not summarize the document. Extract the dates only."
     * No summary preamble, extract only dates.
     */
    public function test_prompt_11_do_not_summarize_extract_dates_only(): void
    {
        $prompt = "Do not summarize the document. Extract the dates only.";
        $intent = $this->classifier->classify($prompt);

        $this->assertContains('forbid_summary', $intent->constraints);
        $this->assertTrue($intent->isOnlyDates());

        $content = "### Executive Summary\nThis document is a service contract.\n\n- 5:00 am - 5:30 am\n- 6:00 am - 7:00 am\n- January 15, 2025\n\nOther notes:\nGeneral daily plan.";
        $cleaned = $this->validator->applyConstraintPostProcessing($content, $intent);

        $this->assertStringNotContainsString('Executive Summary', $cleaned);
        $this->assertStringNotContainsString('service contract', $cleaned);
        $this->assertStringContainsString('5:00 am - 5:30 am', $cleaned);
        $this->assertStringContainsString('January 15, 2025', $cleaned);
    }

    /**
     * TEST 12: "Compare only the responsibilities in these two documents."
     * Focuses on roles/responsibilities.
     */
    public function test_prompt_12_compare_only_responsibilities(): void
    {
        $prompt = "Compare only the responsibilities in these two documents.";
        $intent = $this->classifier->classify($prompt);

        $this->assertTrue($intent->isOnlyResponsibilities());

        $content = "### Roles and Responsibilities\n- Nihal is Product and Vision Lead\n- Gideon is Technical Lead\n\n### Payment Terms\n- Hourly rate is $100/hr\n\n### Termination\n- 30 days notice required";
        $cleaned = $this->validator->applyConstraintPostProcessing($content, $intent);

        $this->assertStringContainsString('Roles and Responsibilities', $cleaned);
        $this->assertStringContainsString('Nihal is Product and Vision Lead', $cleaned);
        $this->assertStringNotContainsString('Payment Terms', $cleaned);
    }

    /**
     * TEST 13: "Return the result as JSON only."
     * Output must parse valid JSON without markdown wrapping.
     */
    public function test_prompt_13_return_result_as_json_only(): void
    {
        $prompt = "Return the result as JSON only.";
        $intent = $this->classifier->classify($prompt);

        $this->assertTrue($intent->isJsonOnly());

        $wrappedJson = "```json\n{\n  \"difference\": \"Schedule vs Roles\",\n  \"status\": \"completed\"\n}\n```";
        $cleaned = $this->validator->applyConstraintPostProcessing($wrappedJson, $intent);

        $this->assertStringNotContainsString('```', $cleaned);
        $decoded = json_decode($cleaned, true);
        $this->assertNotNull($decoded, "Output must be valid parseable JSON");
        $this->assertEquals('Schedule vs Roles', $decoded['difference']);
    }

    /**
     * TEST 14: "Answer only yes or no if the available evidence supports it."
     * Binary answer.
     */
    public function test_prompt_14_answer_only_yes_or_no(): void
    {
        $prompt = "Answer only yes or no if the available evidence supports it.";
        $intent = $this->classifier->classify($prompt);

        $this->assertTrue($intent->isYesNoOnly());

        $yesText = "Yes, based on the uploaded document on page 2, the clause was confirmed.";
        $this->assertEquals("Yes.", $this->validator->applyConstraintPostProcessing($yesText, $intent));

        $noText = "No, the agreement does not support this claim.";
        $this->assertEquals("No.", $this->validator->applyConstraintPostProcessing($noText, $intent));
    }

    /**
     * REGRESSION TEST: Schedule.pdf vs roles assigned.pdf
     * Verifies strict document isolation and absence of hallucinated removed clauses.
     */
    public function test_regression_schedule_vs_roles_isolation(): void
    {
        $docA = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Schedule.pdf',
            'original_name' => 'Schedule.pdf',
            'storage_path' => 'documents/schedule.pdf',
            'file_path' => 'documents/schedule.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 42810,
            'page_count' => 1,
            'checksum_sha256' => hash('sha256', 'schedule_baseline'),
            'slot' => 'A',
            'expires_at' => now()->addDays(7),
        ]);

        $docB = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'roles assigned.pdf',
            'original_name' => 'roles assigned.pdf',
            'storage_path' => 'documents/roles.pdf',
            'file_path' => 'documents/roles.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 115750,
            'page_count' => 2,
            'checksum_sha256' => hash('sha256', 'roles_baseline'),
            'slot' => 'B',
            'expires_at' => now()->addDays(7),
        ]);

        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Schedule.pdf vs roles assigned.pdf',
            'status' => \App\Enums\ComparisonStatus::COMPLETED,
            'document_a_id' => $docA->id,
            'document_b_id' => $docB->id,
        ]);

        $indexManager = app(EphemeralIndexManager::class);
        $indexManager->storeChunks($job->id, 'A', [
            [
                'chunk_id' => 'chunk_a_1',
                'slot' => 'A',
                'page_number' => 1,
                'section_title' => 'Schedule',
                'content' => 'Schedule Wake up at 5:00 am EVERYDAY 5:00 am - 5:30 am = yoga or meditation 6:00 am - 7:00 am = gym',
            ],
        ]);
        $indexManager->storeChunks($job->id, 'B', [
            [
                'chunk_id' => 'chunk_b_1',
                'slot' => 'B',
                'page_number' => 1,
                'section_title' => 'ROLES',
                'content' => 'ROLES Roles for each member for next 2-3months 1. Product and vision lead TAKEN BY NIHAL AND GIDEON RAJ What he does - understands the product vision',
            ],
        ]);

        $orchestrator = app(AIOrchestrator::class);
        $response = $orchestrator->answerComparisonQuery(
            job: $job,
            query: "What is the difference between these two documents and what is Nihal's role in document B?",
            userId: $this->user->id
        );

        $this->assertNotEmpty($response['content']);
        $this->assertStringContainsStringIgnoringCase('Nihal', $response['content']);
        $this->assertStringContainsStringIgnoringCase('Product', $response['content']);
        $this->assertTrue($response['is_grounded']);
    }
}
