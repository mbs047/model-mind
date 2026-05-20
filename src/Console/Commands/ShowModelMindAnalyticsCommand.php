<?php

namespace Mbs\ModelMind\Console\Commands;

use Illuminate\Console\Command;
use Mbs\ModelMind\Support\Analytics\ModelMindAnalytics;

class ShowModelMindAnalyticsCommand extends Command
{
    protected $signature = 'model-mind:analytics
        {--days= : Number of recent days to summarize}
        {--json : Output the analytics summary as JSON}';

    protected $description = 'Summarize ModelMind usage analytics for dashboards and operations.';

    public function handle(ModelMindAnalytics $analytics): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('model-mind.analytics.summary_days', 7)));
        $summary = $analytics->summary($days);

        if ($this->option('json')) {
            $json = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (! is_string($json)) {
                $this->error('Unable to encode the ModelMind analytics summary.');

                return self::FAILURE;
            }

            $this->line($json);

            return self::SUCCESS;
        }

        if (! ($summary['table_ready'] ?? false)) {
            $this->warn('ModelMind analytics table is not available yet.');
            $this->line('Run the package migration in fresh installs, or add the events table to upgraded applications.');

            return self::SUCCESS;
        }

        $totals = $summary['totals'] ?? [];

        $this->info("ModelMind analytics ({$days} day(s))");
        $this->line('Completed answers: '.($totals['completed'] ?? 0));
        $this->line('Failed answers: '.($totals['failed'] ?? 0));
        $this->line('Average latency: '.(($totals['avg_latency_ms'] ?? null) === null ? 'n/a' : $totals['avg_latency_ms'].' ms'));
        $this->line('Token usage: '.($totals['total_tokens'] ?? 0).' total');
        $this->line('Feedback rate: '.round((float) ($totals['feedback_rate'] ?? 0) * 100, 2).'%');
        $this->line('Route/action clicks: '.($totals['action_clicks'] ?? 0));

        $providers = collect($summary['providers'] ?? []);

        if ($providers->isNotEmpty()) {
            $this->newLine();
            $this->line('Providers');
            $providers->each(function (array $provider): void {
                $this->line(sprintf(
                    '- %s/%s: %d completed, %d failed, %s avg latency, %d tokens',
                    (string) ($provider['provider'] ?? 'unknown'),
                    (string) ($provider['model'] ?? 'unknown'),
                    (int) ($provider['completed'] ?? 0),
                    (int) ($provider['failed'] ?? 0),
                    ($provider['avg_latency_ms'] ?? null) === null ? 'n/a' : $provider['avg_latency_ms'].' ms',
                    (int) ($provider['total_tokens'] ?? 0),
                ));
            });
        }

        return self::SUCCESS;
    }
}
