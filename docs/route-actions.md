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

## Settings

```env
MODEL_MIND_MAX_ACTIONS=5
MODEL_MIND_ROUTE_TOKEN=model_mind_route
```

```php
'actions' => [
    'max_actions' => 5,
    'route_token' => 'model_mind_route',
    'allow_label_override' => false,
],
```

Keep `allow_label_override` disabled unless you trust the model to choose button labels. With the default setting, labels always come from your config or model trait.
