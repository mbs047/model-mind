<?php

namespace Mbs\ModelMind\Console\Commands;

use Illuminate\Console\Command;
use Mbs\ModelMind\Support\Presets\ModelMindPresetRepository;

class ShowModelMindPresetCommand extends Command
{
    protected $signature = 'model-mind:preset
        {preset? : Preset name: store, admin, support, docs, or crm}
        {--json : Output the preset recommendation as JSON}
        {--list : List available presets}';

    protected $description = 'Show ModelMind preset recommendations for common application types.';

    public function handle(ModelMindPresetRepository $presets): int
    {
        $presetName = $this->argument('preset');

        if ($this->option('list') || ! is_string($presetName) || blank($presetName)) {
            $this->showList($presets);

            return self::SUCCESS;
        }

        $preset = $presets->find($presetName);

        if ($preset === null) {
            $this->error("Unknown ModelMind preset [{$presetName}].");
            $this->line('Available presets: '.implode(', ', $presets->names()));

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $json = json_encode($preset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (! is_string($json)) {
                $this->error('Unable to encode the ModelMind preset.');

                return self::FAILURE;
            }

            $this->line($json);

            return self::SUCCESS;
        }

        $this->showPreset($preset);

        return self::SUCCESS;
    }

    private function showList(ModelMindPresetRepository $presets): void
    {
        $active = $presets->activeName();

        $this->info('Available ModelMind presets');

        foreach ($presets->all() as $name => $preset) {
            $suffix = $active === $name ? ' (active)' : '';
            $this->line(sprintf(
                '- %s%s: %s',
                $name,
                $suffix,
                (string) ($preset['description'] ?? $preset['label'] ?? ''),
            ));
        }

        $this->newLine();
        $this->line('Preview one with: php artisan model-mind:preset store');
        $this->line('Export JSON with: php artisan model-mind:preset store --json');
    }

    /**
     * @param  array<string, mixed>  $preset
     */
    private function showPreset(array $preset): void
    {
        $this->info((string) ($preset['label'] ?? $preset['name'] ?? 'ModelMind preset'));
        $this->line((string) ($preset['description'] ?? ''));
        $this->newLine();

        $this->line('Recommended questions:');
        foreach ((array) ($preset['questions'] ?? []) as $question) {
            $this->line("- {$question}");
        }

        $this->newLine();
        $this->line('Recommended models:');
        foreach ((array) ($preset['models'] ?? []) as $key => $model) {
            if (! is_array($model)) {
                continue;
            }

            $this->line(sprintf(
                '- %s [%s]: %s',
                (string) ($model['label'] ?? $key),
                (string) ($model['class'] ?? 'App\\Models\\Model'),
                (string) ($model['description'] ?? ''),
            ));
        }

        $this->newLine();
        $this->line('Retrieval: '.$this->inlineJson($preset['retrieval'] ?? []));
        $this->line('Security: '.$this->inlineJson($preset['security'] ?? []));
        $this->line('Route actions: '.implode(', ', (array) ($preset['route_actions'] ?? [])));

        $this->newLine();
        $this->line('Copy the config payload from --json into config/model-mind.php and adjust class names, columns, route names, and authorization for your app.');
    }

    private function inlineJson(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($json) ? $json : '{}';
    }
}
