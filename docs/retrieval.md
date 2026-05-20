# Question-Aware Retrieval

ModelMind sends a cached general context for fast answers, then adds a fresh question-specific context when the visitor asks about a record that may not be in the cached window.

This is important for large catalogs. A product can be enabled in config but still absent from the cached prompt when `limit`, `max_rows_per_model`, `order_by`, or `max_context_characters` exclude it. Retrieval searches enabled models for the current question and adds matching rows near the top of the prompt.

## Configuration

```env
MODEL_MIND_RETRIEVAL_ENABLED=true
MODEL_MIND_RETRIEVAL_LIMIT=8
MODEL_MIND_RETRIEVAL_MAX_TERMS=8
MODEL_MIND_RETRIEVAL_MIN_TERM_LENGTH=2
```

## Per-Model Search Columns

By default, ModelMind searches common text fields such as `name`, `title`, `sku`, `brand`, `category`, `short_description`, and `description`.

For tighter control, add `search_columns` to a model:

```php
'models' => [
    App\Models\Product::class => [
        'enabled' => true,
        'columns' => 'auto',
        'search_columns' => ['sku', 'name', 'brand', 'category', 'short_description', 'description'],
        'limit' => 50,
    ],
],
```

Only columns that are already allowed in the model context can be searched.

## Cached Context

The general context is still cached by `MODEL_MIND_CONTEXT_CACHE_SECONDS`. Retrieval is uncached so newly inserted or recently updated matching records can still be found.

Clear the cached general context after large imports or config changes:

```bash
php artisan model-mind:clear-context
```

If Laravel config is cached, clear it too:

```bash
php artisan optimize:clear
```

## Debugging

Inspect the static context:

```bash
php artisan model-mind:inspect-context --json
```

If a record is missing from the static context but retrieval is enabled, ask for it by a searchable field such as SKU, product name, brand, or category.
