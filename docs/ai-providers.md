# Custom AI Providers

The package resolves `Mbs\ModelMind\Contracts\ModelMindProvider`. Bind your own provider in an application service provider:

```php
use Mbs\ModelMind\Contracts\ModelMindProvider;

public function register(): void
{
    $this->app->bind(ModelMindProvider::class, App\Support\Ai\CustomModelMindProvider::class);
}
```

Your provider receives a `ModelMindRequestData` object and returns `ModelMindResponseData`.

Use a custom provider when you need a different AI service, internal gateway, streaming adapter, or company-specific logging layer.

## Streaming Providers

Existing providers continue to work with the streaming endpoint. If they only implement `ModelMindProvider`, ModelMind emits the full answer as a single stream event.

For true token streaming, also implement `Mbs\ModelMind\Contracts\StreamingModelMindProvider`:

```php
use Mbs\ModelMind\Contracts\ModelMindProvider;
use Mbs\ModelMind\Contracts\StreamingModelMindProvider;
use Mbs\ModelMind\Data\ModelMindRequestData;
use Mbs\ModelMind\Data\ModelMindResponseData;

class CustomModelMindProvider implements ModelMindProvider, StreamingModelMindProvider
{
    public function answer(ModelMindRequestData $request): ModelMindResponseData
    {
        return new ModelMindResponseData('Full answer.');
    }

    public function stream(ModelMindRequestData $request): iterable
    {
        yield 'First ';
        yield 'tokens.';
    }

    public function streamMetadata(ModelMindRequestData $request): array
    {
        return ['model' => 'custom-model'];
    }
}
```

See [Streaming Responses](streaming.md) for endpoint details.
