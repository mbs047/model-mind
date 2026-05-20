# Models and Context

ModelMind only reads models that are explicitly enabled in `config/model-mind.php`.

```php
'models' => [
    App\Models\Product::class => [
        'enabled' => true,
        'label' => 'Products',
        'description' => 'Public product catalog.',
        'columns' => 'auto',
        'include' => ['name', 'sku', 'brand', 'category', 'price'],
        'exclude' => ['internal_notes'],
        'relations' => ['category:id,name'],
        'limit' => 50,
        'order_by' => ['updated_at' => 'desc'],
    ],
],
```

## Options

- `enabled`: must be `true` for the model to be used.
- `label`: human-readable model name in the prompt context.
- `description`: short explanation of what this model means.
- `columns`: use `auto` for safe column discovery, or provide an explicit array.
- `include`: extra columns that should be included when safe.
- `exclude`: columns that should never be sent.
- `relations`: Eloquent relations to eager load with selected columns.
- `limit`: max records for this model before global security limits are applied.
- `order_by`: column and direction pairs used to sort records.
- `search_columns`: allowed columns used by question-aware retrieval.
- `route_actions`: safe Laravel named-route actions that can become chat buttons.

## Inspect Context

Before enabling ModelMind in production, inspect the context that can be sent to the provider:

```bash
php artisan model-mind:inspect-context
php artisan model-mind:inspect-context --json
```

Use this command after every major config change.

## Related Guides

- [Security Controls](security.md)
- [Per-Model Overrides](per-model-overrides.md)
- [Question-Aware Retrieval](retrieval.md)
- [Named Route Actions](route-actions.md)
