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
