# Source Citations

Source citations show which enabled records an answer used. Each citation includes the model label, record label, cited columns, and an optional route button when the record has a configured route action.

## Enable Citations

Citations are enabled by default.

```env
MODEL_MIND_CITATIONS_ENABLED=true
MODEL_MIND_SOURCE_TOKEN=model_mind_source
MODEL_MIND_INFER_SOURCE_CITATIONS=true
MODEL_MIND_MAX_CITATIONS=4
MODEL_MIND_CITATION_COLUMNS=4
```

```php
'features' => [
    'citations' => true,
],

'citations' => [
    'enabled' => true,
    'token' => 'model_mind_source',
    'infer_from_answer' => true,
    'max_citations' => 4,
    'max_columns' => 4,
    'label_columns' => ['name', 'title', 'label', 'sku', 'code', 'slug', 'id'],
],
```

## Record Labels

Use `source_label_column` when a model has a clear display column:

```php
App\Models\Product::class => [
    'enabled' => true,
    'label' => 'Products',
    'columns' => 'auto',
    'source_label_column' => 'name',
],
```

Use `source_label_template` when a label needs more context:

```php
'source_label_template' => '{name} ({sku})',
```

If neither option is set, ModelMind checks `citations.label_columns`, then falls back to the record key.

## How It Works

Each enabled model row gets a safe source token in the application context:

```text
[[model_mind_source key="a1b2c3d4e5f6g7h8"]]
```

The AI is instructed to copy that token when it uses the row. The server validates the token, removes it from the visible answer, and returns a structured citation:

```json
{
    "model": "Products",
    "record": "Samsung Galaxy S24 Ultra",
    "source": "Products: Samsung Galaxy S24 Ultra",
    "columns": ["name", "price", "stock_status"],
    "action": {
        "label": "View Samsung Galaxy S24 Ultra",
        "url": "https://example.test/products/1",
        "kind": "route"
    }
}
```

## Column Attribution

The assistant may include a `columns` attribute:

```text
[[model_mind_source key="a1b2c3d4e5f6g7h8" columns="name, price, stock_status"]]
```

Only columns already present in the enabled model context can be shown. Unknown or unsafe columns are ignored.

## Route Button

When the cited row has a valid `route_actions` entry, the citation can show a button for that record:

```php
'route_actions' => [
    'products.view' => [
        'label' => 'View product',
        'label_column' => 'name',
        'label_template' => 'View {name}',
        'route' => 'products.show',
        'parameters' => ['product' => 'id'],
    ],
],
```

Route buttons still use ModelMind's route action security: route names must be configured, parameters must come from enabled context, and authorization rules are checked before the URL is returned.

## Inference

`MODEL_MIND_INFER_SOURCE_CITATIONS=true` lets the server recover citations when the model clearly mentions an enabled record but does not copy the source token. This is useful for multilingual chats because visitors can ask in any language while the database remains in one language.

Disable inference if you only want citations when exact source tokens are present:

```env
MODEL_MIND_INFER_SOURCE_CITATIONS=false
```

## Security Notes

- Citations are built from the same filtered model context as answers.
- Hidden, excluded, blocked, unauthorized, or tenant-scoped-out records cannot be cited.
- Source tokens are not trusted from the AI. The server validates the key against the current enabled context.
- Route buttons inside citations are resolved server-side with Laravel named routes.
