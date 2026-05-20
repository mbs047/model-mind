# Provider Drivers

ModelMind uses OpenAI by default, but the provider layer is configurable. You can switch drivers without changing controllers, routes, Blade views, sessions, feedback, route actions, citations, or learning memory.

## Available Drivers

- `openai` - default built-in OpenAI Responses API driver.
- `anthropic` - Anthropic Messages API driver.
- `gemini` - Google Gemini generate content driver.
- `ollama` - local Ollama chat driver.
- `custom` - your own class implementing `Mbs\ModelMind\Contracts\ModelMindProvider`.

Set the active driver:

```env
MODEL_MIND_PROVIDER=openai
```

## OpenAI

```env
MODEL_MIND_PROVIDER=openai
OPENAI_API_KEY=sk-your-key
OPENAI_ORGANIZATION=org-your-organization-id
MODEL_MIND_MODEL=gpt-5-nano
MODEL_MIND_OPENAI_BASE_URL=https://api.openai.com/v1
```

The legacy OpenAI settings remain the default package settings, so existing installs keep working.

## Anthropic

```env
MODEL_MIND_PROVIDER=anthropic
MODEL_MIND_ANTHROPIC_API_KEY=sk-ant-your-key
MODEL_MIND_ANTHROPIC_MODEL=claude-3-5-haiku-latest
MODEL_MIND_ANTHROPIC_VERSION=2023-06-01
MODEL_MIND_ANTHROPIC_MAX_OUTPUT_TOKENS=450
```

The driver sends ModelMind instructions as the Anthropic system prompt and sends the application-aware question prompt as the user message.

## Gemini

```env
MODEL_MIND_PROVIDER=gemini
MODEL_MIND_GEMINI_API_KEY=your-google-ai-key
MODEL_MIND_GEMINI_MODEL=gemini-2.0-flash
MODEL_MIND_GEMINI_MAX_OUTPUT_TOKENS=450
```

Gemini can also read `GEMINI_API_KEY` or `GOOGLE_API_KEY` when `MODEL_MIND_GEMINI_API_KEY` is not set.

## Ollama

```env
MODEL_MIND_PROVIDER=ollama
MODEL_MIND_OLLAMA_BASE_URL=http://127.0.0.1:11434
MODEL_MIND_OLLAMA_MODEL=llama3.1
MODEL_MIND_OLLAMA_TIMEOUT=30
```

Ollama runs locally, so no API key is required. You can publish the config and set `provider.drivers.ollama.options` for model options such as temperature.

## Custom Provider

```env
MODEL_MIND_PROVIDER=custom
MODEL_MIND_CUSTOM_PROVIDER=App\Support\Ai\ModelMindGatewayProvider
```

```php
use Mbs\ModelMind\Contracts\ModelMindProvider;
use Mbs\ModelMind\Data\ModelMindRequestData;
use Mbs\ModelMind\Data\ModelMindResponseData;

class ModelMindGatewayProvider implements ModelMindProvider
{
    public function answer(ModelMindRequestData $request): ModelMindResponseData
    {
        return new ModelMindResponseData('Answer from your gateway.', [
            'provider' => 'gateway',
        ]);
    }
}
```

For true streaming, also implement `Mbs\ModelMind\Contracts\StreamingModelMindProvider`.

## Config Registry

Published config exposes a driver registry:

```php
'provider' => [
    'default' => env('MODEL_MIND_PROVIDER', 'openai'),

    'drivers' => [
        'openai' => [
            'class' => Mbs\ModelMind\Support\Providers\OpenAiModelMindProvider::class,
        ],
        'anthropic' => [
            'class' => Mbs\ModelMind\Support\Providers\AnthropicModelMindProvider::class,
            'api_key' => env('MODEL_MIND_ANTHROPIC_API_KEY', env('ANTHROPIC_API_KEY')),
            'model' => env('MODEL_MIND_ANTHROPIC_MODEL', 'claude-3-5-haiku-latest'),
        ],
        'gemini' => [
            'class' => Mbs\ModelMind\Support\Providers\GeminiModelMindProvider::class,
            'api_key' => env('MODEL_MIND_GEMINI_API_KEY', env('GEMINI_API_KEY', env('GOOGLE_API_KEY'))),
            'model' => env('MODEL_MIND_GEMINI_MODEL', 'gemini-2.0-flash'),
        ],
        'ollama' => [
            'class' => Mbs\ModelMind\Support\Providers\OllamaModelMindProvider::class,
            'base_url' => env('MODEL_MIND_OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
            'model' => env('MODEL_MIND_OLLAMA_MODEL', 'llama3.1'),
        ],
        'custom' => [
            'class' => env('MODEL_MIND_CUSTOM_PROVIDER'),
        ],
    ],
],
```

You can also register another named driver by adding a new entry under `provider.drivers` whose `class` implements `ModelMindProvider`, then set `MODEL_MIND_PROVIDER` to that driver key.

## Streaming

The built-in OpenAI, Anthropic, Gemini, and Ollama drivers implement the streaming contract. Enable the stream UI with:

```env
MODEL_MIND_STREAMING=true
```

See [Streaming Responses](streaming.md) for the SSE event contract.
