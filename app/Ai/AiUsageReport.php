<?php

namespace App\Ai;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates ai_usage_logs into per-feature and per-model usage with an estimated cost.
 * Prices (USD per 1M tokens) come from config/openai.php and are estimates, not invoices.
 */
class AiUsageReport
{
    public function build(int $days = 7): array
    {
        $since = now()->subDays($days)->startOfDay();

        $rows = AiUsageLog::where('created_at', '>=', $since)
            ->select('feature', 'model', DB::raw('COUNT(*) as calls'),
                DB::raw("SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors"),
                DB::raw('SUM(prompt_tokens) as prompt_tokens'),
                DB::raw('SUM(completion_tokens) as completion_tokens'),
                DB::raw('AVG(latency_ms) as avg_latency_ms'))
            ->groupBy('feature', 'model')
            ->get();

        $features = [];
        $total = 0.0;
        foreach ($rows as $r) {
            $cost = $this->cost($r->model, (int) $r->prompt_tokens, (int) $r->completion_tokens);
            $total += $cost;
            $features[] = [
                'feature' => $r->feature,
                'model' => $r->model,
                'calls' => (int) $r->calls,
                'errors' => (int) $r->errors,
                'prompt_tokens' => (int) $r->prompt_tokens,
                'completion_tokens' => (int) $r->completion_tokens,
                'avg_latency_ms' => (int) round($r->avg_latency_ms),
                'est_cost_usd' => round($cost, 4),
            ];
        }

        usort($features, fn($a, $b) => $b['est_cost_usd'] <=> $a['est_cost_usd']);

        return [
            'days' => $days,
            'total_calls' => array_sum(array_column($features, 'calls')),
            'total_errors' => array_sum(array_column($features, 'errors')),
            'est_cost_usd' => round($total, 4),
            'by_feature' => $features,
        ];
    }

    private function cost(string $model, int $promptTokens, int $completionTokens): float
    {
        $price = config("openai.prices.$model");
        if (!$price) {
            return 0.0;
        }
        return ($promptTokens * $price['in'] + $completionTokens * $price['out']) / 1_000_000;
    }
}
