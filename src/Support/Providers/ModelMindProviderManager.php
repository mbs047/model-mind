<?php

namespace Mbs\ModelMind\Support\Providers;

use Mbs\ModelMind\Contracts\ModelMindProvider;
use RuntimeException;

class ModelMindProviderManager
{
    /**
     * @var array<string, class-string<ModelMindProvider>>
     */
    private array $defaults = [
        'openai' => OpenAiModelMindProvider::class,
        'anthropic' => AnthropicModelMindProvider::class,
        'claude' => AnthropicModelMindProvider::class,
        'gemini' => GeminiModelMindProvider::class,
        'google' => GeminiModelMindProvider::class,
        'ollama' => OllamaModelMindProvider::class,
    ];

    public function resolve(): ModelMindProvider
    {
        $driver = $this->driver();
        $providerClass = $this->providerClass($driver);
        $provider = app($providerClass);

        if (! $provider instanceof ModelMindProvider) {
            throw new RuntimeException("The configured ModelMind provider [{$providerClass}] must implement ".ModelMindProvider::class.'.');
        }

        return $provider;
    }

    public function driver(): string
    {
        $driver = config('model-mind.provider.default', 'openai');

        if (! is_string($driver) || blank($driver)) {
            return 'openai';
        }

        return str($driver)->lower()->replace(['-', ' '], '_')->toString();
    }

    /**
     * @return class-string<ModelMindProvider>
     */
    private function providerClass(string $driver): string
    {
        $configuredClass = config("model-mind.provider.drivers.{$driver}.class");

        if (! is_string($configuredClass) || blank($configuredClass)) {
            $configuredClass = $driver === 'custom'
                ? config('model-mind.provider.custom')
                : null;
        }

        $providerClass = is_string($configuredClass) && filled($configuredClass)
            ? $configuredClass
            : ($this->defaults[$driver] ?? null);

        if (! is_string($providerClass) || blank($providerClass) || ! class_exists($providerClass)) {
            throw new RuntimeException("The ModelMind provider driver [{$driver}] is not configured.");
        }

        return $providerClass;
    }
}
