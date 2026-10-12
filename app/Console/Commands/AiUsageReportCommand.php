<?php

namespace App\Console\Commands;

use App\Ai\AiUsageReport;
use Illuminate\Console\Command;

class AiUsageReportCommand extends Command
{
    protected $signature = 'ai:usage-report {--days=7 : How many days back to include}';

    protected $description = 'Show OpenAI calls, errors, tokens and estimated cost per feature';

    public function handle(AiUsageReport $report)
    {
        $data = $report->build(max(1, (int) $this->option('days')));

        $this->table(
            ['Feature', 'Model', 'Calls', 'Errors', 'Tokens in', 'Tokens out', 'Avg ms', 'Est. USD'],
            array_map(fn($r) => [
                $r['feature'], $r['model'], $r['calls'], $r['errors'],
                $r['prompt_tokens'], $r['completion_tokens'], $r['avg_latency_ms'], number_format($r['est_cost_usd'], 4),
            ], $data['by_feature'])
        );

        $this->info("Last {$data['days']} days: {$data['total_calls']} calls, {$data['total_errors']} errors, est. \${$data['est_cost_usd']}");
    }
}
