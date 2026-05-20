# Question-Aware Retrieval

ModelMind sends a cached general context for fast answers, then adds a fresh question-specific context when the visitor asks about a record that may not be in the cached window.

This is important for large catalogs. A product can be enabled in config but still absent from the cached prompt when `limit`, `max_rows_per_model`, `order_by`, or `max_context_characters` exclude it. Retrieval searches enabled models for the current question and adds matching rows near the top of the prompt.

## Configuration

```env
MODEL_MIND_RETRIEVAL_ENABLED=true
MODEL_MIND_RETRIEVAL_LIMIT=8
MODEL_MIND_RETRIEVAL_CANDIDATE_LIMIT=60
MODEL_MIND_RETRIEVAL_MAX_TERMS=8
MODEL_MIND_RETRIEVAL_MIN_TERM_LENGTH=2
MODEL_MIND_RETRIEVAL_MIN_SCORE=0.1
MODEL_MIND_RETRIEVAL_FUZZY=true
MODEL_MIND_RETRIEVAL_MULTILINGUAL=true
MODEL_MIND_RETRIEVAL_SCOUT=false
MODEL_MIND_RETRIEVAL_VECTOR=false
```

ModelMind now ranks retrieved records instead of only returning the first SQL `LIKE` matches. The default engine:

- Normalizes visitor questions and record text.
- Strips diacritics and normalizes common multilingual forms.
- Scores records by weighted column matches.
- Adds fuzzy matching for misspellings.
- Falls back to a bounded candidate window when fuzzy matching is needed.

The question context includes retrieval metadata:

```json
{
    "retrieval": {
        "engine": "ranked_database",
        "ranked": true,
        "columns": ["name", "sku", "description"],
        "weights": {"name": 10, "sku": 12, "description": 2},
        "scores": [
            {"record": "Samsung Galaxy S24 Ultra", "score": 38.5, "columns": ["name", "sku"]}
        ]
    }
}
```

Disable score metadata if you want a smaller prompt:

```env
MODEL_MIND_RETRIEVAL_INCLUDE_SCORES=false
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

## Weighted Columns

Use associative `search_columns` when some fields should rank higher:

```php
'models' => [
    App\Models\Product::class => [
        'enabled' => true,
        'columns' => 'auto',
        'search_columns' => [
            'sku' => 12,
            'name' => 10,
            'brand' => 6,
            'description' => 2,
        ],
    ],
],
```

Or keep `search_columns` as a simple list and use `retrieval_weights`:

```php
'search_columns' => ['name', 'sku', 'description'],
'retrieval_weights' => [
    'sku' => 12,
    'name' => 10,
    'description' => 2,
],
```

Global fallback weights live in `config/model-mind.php` under `retrieval.column_weights`.

## Fuzzy Matching

Fuzzy matching helps with typo-heavy searches:

```env
MODEL_MIND_RETRIEVAL_FUZZY=true
MODEL_MIND_RETRIEVAL_FUZZY_DISTANCE=2
MODEL_MIND_RETRIEVAL_FUZZY_SIMILARITY=0.72
```

Example: `samsng galxy` can still retrieve `Samsung Galaxy S24 Ultra` when that record is inside the bounded candidate window.

## Multilingual Normalization

The retrieval normalizer lowercases text, strips diacritics, optionally transliterates when PHP's intl extension is available, and normalizes common Arabic forms such as alef variants, ya, ta marbuta, tatweel, and harakat.

```env
MODEL_MIND_RETRIEVAL_MULTILINGUAL=true
```

This does not translate your data. It makes matching more forgiving when users ask in languages that use diacritics or alternate character forms.

## Optional Scout Support

If a model uses Laravel Scout's `Searchable` trait, ModelMind can ask Scout for candidates first:

```env
MODEL_MIND_RETRIEVAL_SCOUT=true
MODEL_MIND_RETRIEVAL_SCOUT_LIMIT=20
```

Scout results are still re-queried through ModelMind's authorized context query before records enter the prompt.

## Optional Vector Search

For embeddings or a vector database, implement `Mbs\ModelMind\Contracts\ModelMindVectorSearcher`:

```php
use Mbs\ModelMind\Contracts\ModelMindVectorSearcher;

class ProductVectorSearcher implements ModelMindVectorSearcher
{
    public function search(string $question, string $modelClass, array $settings, array $columns, int $limit): iterable
    {
        return app(VectorIndex::class)
            ->nearest($question, model: $modelClass, limit: $limit)
            ->pluck('record_id');
    }
}
```

Then enable it:

```env
MODEL_MIND_RETRIEVAL_VECTOR=true
MODEL_MIND_VECTOR_SEARCHER=App\Support\ModelMind\ProductVectorSearcher
MODEL_MIND_RETRIEVAL_VECTOR_LIMIT=12
```

The searcher may return model instances, primary keys, or arrays with `id`, `key`, `model`, or `record`. ModelMind validates the final records through the normal context query and authorization checks.

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
