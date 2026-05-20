<?php

namespace Mbs\LaravelAiChat\Support\Context;

use Illuminate\Support\Facades\Cache;
use Mbs\LaravelAiChat\Contracts\AiChatContextProvider;

class ContextRegistry
{
    public function __construct(private readonly ModelContextBuilder $modelContextBuilder) {}

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        $seconds = (int) config('mbs-ai-chat.memory.context_cache_seconds', 300);

        if ($seconds <= 0) {
            return $this->uncachedContext();
        }

        return Cache::remember('mbs-ai-chat.context.v1', $seconds, fn (): array => $this->uncachedContext());
    }

    public function toPrompt(): string
    {
        $encoded = json_encode($this->context(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return str($encoded ?: '{}')
            ->limit((int) config('mbs-ai-chat.security.max_context_characters', 24000), '')
            ->toString();
    }

    /**
     * @return array<string, mixed>
     */
    private function uncachedContext(): array
    {
        return [
            'source_policy' => config('mbs-ai-chat.prompt.source_policy'),
            'configured_context' => $this->configuredContext(),
            'models' => $this->modelContexts(),
            'providers' => $this->providerContexts(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function configuredContext(): array
    {
        $context = config('mbs-ai-chat.context', []);

        return is_array($context) ? $context : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function modelContexts(): array
    {
        $models = config('mbs-ai-chat.models', []);

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
        return collect(config('mbs-ai-chat.context_providers', []))
            ->map(function (mixed $provider): array {
                if (! is_string($provider) || ! class_exists($provider)) {
                    return [];
                }

                $instance = app($provider);

                return $instance instanceof AiChatContextProvider
                    ? $instance->context()
                    : [];
            })
            ->filter()
            ->values()
            ->all();
    }
}
