<?php

namespace Mbs\ModelMind\Console\Commands;

use Illuminate\Console\Command;
use Mbs\ModelMind\Support\Context\ContextRegistry;

class InspectModelMindContextCommand extends Command
{
    protected $signature = 'model-mind:inspect-context
        {--json : Output the context as JSON}
        {--no-redact : Do not redact suspicious keys from custom providers}';

    protected $description = 'Inspect the safe context ModelMind will send to the provider.';

    public function handle(ContextRegistry $contextRegistry): int
    {
        $context = $contextRegistry->context();

        if (! $this->option('no-redact')) {
            $context = $this->redact($context);
        }

        $json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($json)) {
            $this->error('Unable to encode the ModelMind context.');

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line($json);

            return self::SUCCESS;
        }

        $models = collect($context['models'] ?? []);

        $this->info('ModelMind context inspection');
        $this->line('Configured model contexts: '.$models->count());
        $models->each(function (array $model): void {
            $this->line(sprintf(
                '- %s: %d row(s), %d column(s)',
                (string) ($model['label'] ?? $model['model'] ?? 'Unknown model'),
                count((array) ($model['rows'] ?? [])),
                count((array) ($model['columns'] ?? [])),
            ));
        });

        $this->newLine();
        $this->line('Use --json to view the complete filtered context.');

        return self::SUCCESS;
    }

    private function redact(mixed $value, ?string $key = null): mixed
    {
        if (is_string($key) && $this->isSensitiveKey($key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item, string|int $itemKey): mixed => $this->redact($item, is_string($itemKey) ? $itemKey : null))
                ->all();
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        if (in_array($key, (array) config('model-mind.security.blocked_columns', []), true)) {
            return true;
        }

        foreach ((array) config('model-mind.security.blocked_patterns', []) as $pattern) {
            if (@preg_match($pattern, $key) === 1) {
                return true;
            }
        }

        return false;
    }
}
