<?php

namespace Mbs\ModelMind\Support\Context;

use Illuminate\Support\Facades\Cache;
use Mbs\ModelMind\Contracts\ModelMindContextProvider;
use Mbs\ModelMind\Support\Auth\ModelMindAuthorization;
use Mbs\ModelMind\Support\Learning\LearningRepository;

class ContextRegistry
{
    public function __construct(
        private readonly ModelContextBuilder $modelContextBuilder,
        private readonly LearningRepository $learningRepository,
        private readonly ModelMindAuthorization $authorization,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function context(?string $question = null): array
    {
        $context = $this->baseContext();
        $questionContext = $this->questionContext($question);

        if ($questionContext === []) {
            return $context;
        }

        $sourcePolicy = $context['source_policy'] ?? config('model-mind.prompt.source_policy');
        unset($context['source_policy']);

        return [
            'source_policy' => $sourcePolicy,
            'question_context' => $questionContext,
            ...$context,
        ];
    }

    public function toPrompt(?string $question = null): string
    {
        $encoded = json_encode($this->context($question), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return str($encoded ?: '{}')
            ->limit((int) config('model-mind.security.max_context_characters', 24000), '')
            ->toString();
    }

    /**
     * @return array<string, mixed>
     */
    private function baseContext(): array
    {
        $seconds = (int) config('model-mind.memory.context_cache_seconds', 300);

        if ($seconds <= 0 || $this->authorization->requiresUncachedContext()) {
            return $this->uncachedContext();
        }

        return Cache::remember('model-mind.context.v1', $seconds, fn (): array => $this->uncachedContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function questionContext(?string $question): array
    {
        if (! (bool) config('model-mind.retrieval.enabled', true) || ! is_string($question) || blank($question)) {
            return [];
        }

        $models = config('model-mind.models', []);

        if (! is_array($models)) {
            return [];
        }

        $contexts = [];

        foreach ($models as $modelClass => $settings) {
            if (is_int($modelClass) && is_string($settings)) {
                $modelClass = $settings;
                $settings = [];
            }

            if (! is_string($modelClass) || ! is_array($settings)) {
                continue;
            }

            $context = $this->modelContextBuilder->buildForQuestion($modelClass, $settings, $question);

            if ($context !== []) {
                $contexts[] = $context;
            }
        }

        if ($contexts === []) {
            return [];
        }

        return [
            'question' => str($question)->squish()->limit(300, '')->toString(),
            'models' => $contexts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function uncachedContext(): array
    {
        $context = [
            'source_policy' => config('model-mind.prompt.source_policy'),
            'configured_context' => $this->configuredContext(),
            'fed_texts' => $this->learningRepository->fedTextContext(),
            'learned_knowledge' => $this->learningRepository->learnedContext(),
            'models' => $this->modelContexts(),
            'providers' => $this->providerContexts(),
        ];

        $authorization = $this->authorization->context();

        if ($authorization !== []) {
            $context = [
                'source_policy' => $context['source_policy'],
                'authorization' => $authorization,
                ...collect($context)->except('source_policy')->all(),
            ];
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function configuredContext(): array
    {
        $context = config('model-mind.context', []);

        return is_array($context) ? $context : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function modelContexts(): array
    {
        $models = config('model-mind.models', []);

        if (! is_array($models)) {
            return [];
        }

        return collect($models)
            ->map(function (mixed $settings, string|int $modelClass): array {
                if (is_int($modelClass) && is_string($settings)) {
                    $modelClass = $settings;
                    $settings = [];
                }

                if (! is_string($modelClass) || ! is_array($settings)) {
                    return [];
                }

                return $this->modelContextBuilder->build($modelClass, $settings);
            })
            ->filter(fn (array $context): bool => $context !== [])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function providerContexts(): array
    {
        return collect(config('model-mind.context_providers', []))
            ->map(function (mixed $provider): array {
                if (! is_string($provider) || ! class_exists($provider)) {
                    return [];
                }

                $instance = app($provider);

                return $instance instanceof ModelMindContextProvider
                    ? $instance->context()
                    : [];
            })
            ->filter()
            ->values()
            ->all();
    }
}
