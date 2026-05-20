# Named Route Actions

ModelMind can turn approved Laravel named routes into clickable chat buttons. This is safer than asking the AI to invent URLs because the package only resolves routes that you explicitly configure.

## Per-Model Route Action

```php
'models' => [
    App\Models\Product::class => [
        'enabled' => true,
        'columns' => 'auto',
        'route_actions' => [
            'products.view' => [
                'label' => 'View product',
                'label_column' => 'name',
                'label_template' => 'View {name}',
                'description' => 'Open the product detail page.',
                'route' => 'products.show',
                'parameters' => ['product' => 'id'],
                'kind' => 'route',
            ],
        ],
    ],
],
```

## Global Route Action

```php
'actions' => [
    'routes' => [
        'orders.view' => [
            'label' => 'View order',
            'route' => 'orders.show',
            'parameters' => ['order' => 'id'],
        ],
    ],
],
```

## Parameter Mapping

The `parameters` array maps Laravel route parameter names to model context fields:

```php
'parameters' => [
    'product' => 'id',
    'tenant' => 'tenant_id',
],
```

For a route like this:

```php
Route::get('/products/{product}', ProductShowController::class)->name('products.show');
```

Use:

```php
'route' => 'products.show',
'parameters' => ['product' => 'id'],
```

## Assistant Tokens

When a row has enough data to build a route action, ModelMind adds a route token to the enabled context:

```text
[[model_mind_route key="products.view" product="123"]]
```

The AI is instructed to copy only approved route tokens. The server validates the key and parameter names, generates the URL with Laravel's `route()` helper, removes the token from the visible answer, and returns a button action.

ModelMind also protects multilingual chats. If the AI answers in Arabic or another language and describes a configured record instead of copying the route token, the server can infer the approved route action from clearly mentioned enabled record labels. It still only creates buttons from your configured route actions.

## Button Labels

Use `label` for a static button label:

```php
'label' => 'View product',
```

For repeated record actions, use `label_column` to show the record value instead of repeating the same static label:

```php
'label' => 'View product',
'label_column' => 'name',
```

With `label_column`, buttons can render as `Samsung Galaxy S24 Ultra`, `Sony WH-1000XM5 Headphones`, and so on.

For the most polished result, use `label_template`:

```php
'label' => 'View product',
'label_column' => 'name',
'label_template' => 'View {name}',
```

Templates can use model column placeholders such as `{name}`, `{sku}`, `{title}`, plus `{label}` for the static label and `{value}` for the configured `label_column` value.

Dynamic labels are resolved server-side from the configured model record, not trusted from the AI response. Global route actions without a model continue to use the static `label` unless `allow_label_override` is enabled.

## Settings

```env
MODEL_MIND_MAX_ACTIONS=5
MODEL_MIND_ROUTE_TOKEN=model_mind_route
MODEL_MIND_INFER_ROUTE_ACTIONS=true
MODEL_MIND_ROUTE_ACTION_INFERENCE_LIMIT=50
```

```php
'actions' => [
    'max_actions' => 5,
    'route_token' => 'model_mind_route',
    'allow_label_override' => false,
    'infer_from_answer' => true,
    'inference_limit' => 50,
],
```

Keep `allow_label_override` disabled unless you trust the model to choose button labels. With the default setting, labels always come from your config or model trait.

Keep `infer_from_answer` enabled when your visitors may chat in multiple languages. Disable it only if you want links to appear exclusively when the AI copies exact route tokens.
