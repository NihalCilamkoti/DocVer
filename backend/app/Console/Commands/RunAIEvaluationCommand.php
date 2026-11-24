<?php

namespace App\Console\Commands;

use App\Services\AI\Evaluation\AIEvaluationEngine;
use Illuminate\Console\Command;

class RunAIEvaluationCommand extends Command
{
    protected $signature = 'ai:evaluate
                            {--category=all : Specific category or "all"}
                            {--provider=groq : AI provider to evaluate (groq, gemini, openrouter)}
                            {--limit= : Maximum number of test cases to evaluate per category}';

    protected $description = 'Run the automated AI performance audit and regression evaluation suite';

    public function handle(AIEvaluationEngine $engine): int
    {
        $category = (string) $this->option('category');
        $provider = (string) $this->option('provider');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $this->info("================================================================================");
        $this->info("           DOCVER AI PERFORMANCE EVALUATION & REGRESSION BENCHMARK              ");
        $this->info("================================================================================");
        $this->line("Provider: <comment>{$provider}</comment> | Category: <comment>{$category}</comment> | Limit per category: <comment>" . ($limit ?: 'All') . "</comment>");
        $this->newLine();

        $progressBar = null;

        $run = $engine->runBenchmark(
            category: $category,
            provider: $provider,
            limit: $limit,
            progressCallback: function ($current, $total, $testId, $passed) use (&$progressBar) {
                if ($progressBar === null) {
                    $progressBar = $this->output->createProgressBar($total);
                    $progressBar->start();
                }
                $progressBar->advance();
            }
        );

        if ($progressBar) {
            $progressBar->finish();
            $this->newLine(2);
        }

        $this->info("================================================================================");
        $this->info("                             EVALUATION SUMMARY                                 ");
        $this->info("================================================================================");

        $this->table(
            ['Metric', 'Value'],
            [
                ['Run ID', $run->run_id],
                ['Provider / Model', "{$run->provider} ({$run->model})"],
                ['Total Tests Executed', $run->total_tests],
                ['Passed Tests', "<info>{$run->passed_tests}</info>"],
                ['Failed Tests', $run->failed_tests > 0 ? "<error>{$run->failed_tests}</error>" : "<info>0</info>"],
                ['Overall Accuracy', "{$run->overall_accuracy}%"],
                ['Instruction Following Accuracy', "{$run->instruction_accuracy}%"],
                ['Citation Accuracy', "{$run->citation_accuracy}%"],
                ['Grounding Accuracy', "{$run->grounding_accuracy}%"],
                ['Hallucination Rate', "{$run->hallucination_rate}%"],
                ['Average Latency', "{$run->avg_latency_ms} ms"],
                ['Total Tokens', number_format($run->total_tokens)],
                ['Run Duration', round($run->duration_ms / 1000, 2) . " s"],
            ]
        );

        $categoryStats = $run->metrics['category_stats'] ?? [];
        if (!empty($categoryStats)) {
            $this->newLine();
            $this->info("Task-Wise Category Performance:");
            $rows = [];
            foreach ($categoryStats as $cat => $stats) {
                $acc = $stats['total'] > 0 ? round(($stats['passed'] / $stats['total']) * 100, 1) : 0;
                $rows[] = [
                    ucwords(str_replace('_', ' ', $cat)),
                    $stats['total'],
                    $stats['passed'],
                    $stats['failed'],
                    "{$acc}%",
                ];
            }
            $this->table(['Category', 'Total', 'Passed', 'Failed', 'Accuracy'], $rows);
        }

        $this->info("\nEvaluation run #{$run->run_id} successfully recorded in database.");
        return Command::SUCCESS;
    }
}
