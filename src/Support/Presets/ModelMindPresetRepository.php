<?php

namespace Mbs\ModelMind\Support\Presets;

use Illuminate\Support\Arr;

class ModelMindPresetRepository
{
    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return collect($this->all())
            ->keys()
            ->filter(fn (mixed $name): bool => is_string($name))
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return collect((array) config('model-mind.presets', []))
            ->filter(fn (mixed $preset): bool => is_array($preset))
            ->map(fn (array $preset): array => $preset)
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(?string $name): ?array
    {
        $name = $this->normalizeName($name);

        if ($name === '') {
            return null;
        }

        $preset = $this->all()[$name] ?? null;

        return is_array($preset) ? $this->expand($name, $preset) : null;
    }

    public function activeName(): ?string
    {
        $name = $this->normalizeName(config('model-mind.preset'));

        return $name !== '' && array_key_exists($name, $this->all()) ? $name : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function active(): ?array
    {
        return $this->find($this->activeName());
    }

    /**
     * @param  array<string, mixed>  $preset
     * @return array<string, mixed>
     */
    private function expand(string $name, array $preset): array
    {
        $questions = $this->questions($preset);
        $models = $this->modelConfig($preset);

        return [
            'name' => $name,
            ...$preset,
            'questions' => $questions,
            'config' => [
                'assistant' => [
                    'default_questions' => $questions,
                ],
                'models' => $models,
                'retrieval' => $this->arrayValue($preset['retrieval'] ?? []),
                'security' => $this->arrayValue($preset['security'] ?? []),
                'route_actions' => $this->arrayValue($preset['route_actions'] ?? []),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $preset
     * @return array<string, array<string, mixed>>
     */
    private function modelConfig(array $preset): array
    {
        return collect((array) ($preset['models'] ?? []))
            ->filter(fn (mixed $model): bool => is_array($model) && is_string($model['class'] ?? null) && filled($model['class']))
            ->mapWithKeys(fn (array $model): array => [
                (string) $model['class'] => Arr::except($model, ['class']),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $preset
     * @return array<int, string>
     */
    private function questions(array $preset): array
    {
        return collect((array) ($preset['questions'] ?? []))
            ->filter(fn (mixed $question): bool => is_scalar($question) && filled((string) $question))
            ->map(fn (mixed $question): string => str(strip_tags((string) $question))->squish()->limit(140, '')->toString())
            ->values()
            ->all();
    }

    /**
     * @return array<mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function normalizeName(mixed $name): string
    {
        if (! is_scalar($name)) {
            return '';
        }

        return str((string) $name)
            ->lower()
            ->replaceMatches('/[^a-z0-9_-]+/', '-')
            ->trim('-')
            ->toString();
    }
}
