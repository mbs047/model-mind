# Custom Context Providers

Use a custom context provider for non-Eloquent data, computed summaries, analytics, or external sources.

```php
use Mbs\ModelMind\Contracts\ModelMindContextProvider;

class SupportPolicyContextProvider implements ModelMindContextProvider
{
    public function toModelMindContext(): array
    {
        return [
            [
                'label' => 'Support policy',
                'description' => 'Support operating rules.',
                'records' => [
                    ['response_time' => 'One business day'],
                ],
            ],
        ];
    }
}
```

Register it:

```php
'context_providers' => [
    App\Support\SupportPolicyContextProvider::class,
],
```

Custom providers are merged with configured model context before the prompt is built. Keep provider output compact and safe.
