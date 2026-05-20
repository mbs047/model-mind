# ModelMind Usage Guide

ModelMind is a Laravel package for adding a secure, model-aware AI chat assistant to Blade applications. This guide explains the full package surface: installation, rendering, model context, security, learning, customization, testing, and release workflow.

## Requirements

- PHP 8.3 or newer.
- Laravel 11, 12, or 13.
- A database supported by Laravel migrations.
- Tailwind CSS in the host application for the default design.
- An OpenAI API key when using the built-in OpenAI provider.

## Installation

Install the package with Composer:

```bash
composer require mbs047/model-mind
```

Publish the config and migrations:

```bash
php artisan model-mind:install
```

Run the migrations:

```bash
php artisan migrate
```

To also publish the Blade views for customization:

```bash
php artisan model-mind:install --views
```

You can publish individual groups manually:

```bash
php artisan vendor:publish --tag=model-mind-config
php artisan vendor:publish --tag=model-mind-migrations
php artisan vendor:publish --tag=model-mind-views
```

## OpenAI Configuration

Use the standard OpenAI environment variables:

```env
OPENAI_API_KEY=sk-your-key
OPENAI_ORGANIZATION=org-your-organization-id
MODEL_MIND_MODEL=gpt-5-nano
```

If ModelMind should use different credentials from the rest of the application, use package-specific values:

```env
MODEL_MIND_OPENAI_API_KEY=sk-your-package-key
MODEL_MIND_OPENAI_ORGANIZATION=org-your-package-organization-id
```

Useful provider settings:

```env
MODEL_MIND_PROVIDER=openai
MODEL_MIND_TIMEOUT=12
MODEL_MIND_CONNECT_TIMEOUT=3
MODEL_MIND_MAX_OUTPUT_TOKENS=450
MODEL_MIND_REASONING_EFFORT=minimal
MODEL_MIND_RETRY_WHEN_TRUNCATED=false
MODEL_MIND_STORE_RESPONSES=false
```

## Rendering the Widget

Add the default widget to a Blade layout:

```blade
@modelMindStyles
@modelMindModal
@modelMindScripts
```

Or render all three parts at once:

```blade
@modelMind
```

The split directives are useful when your layout needs styles in the head and scripts before the closing body tag:

```blade
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @modelMindStyles
</head>
<body>
    {{ $slot }}

    @modelMindModal
    @modelMindScripts
</body>
```

## Blade Components

The default modal can also be rendered as an anonymous component:

```blade
<x-model-mind::modal />
```

The package directives are preferred for complete installations because they keep modal, styles, and scripts together.

## UI Configuration

The default design is Tailwind-friendly and supports standard light and dark variants.

```env
MODEL_MIND_BRAND_MARK=MBS
MODEL_MIND_NAME=ModelMind
MODEL_MIND_SUBTITLE="AI assistant powered by your application data"
MODEL_MIND_LAUNCHER_LABEL="Ask ModelMind"
MODEL_MIND_PLACEHOLDER="Ask about the enabled application data"
MODEL_MIND_THEME=auto
MODEL_MIND_POSITION=bottom-right
MODEL_MIND_WIDTH=25rem
MODEL_MIND_OFFSET=1.25rem
MODEL_MIND_Z_INDEX=9999
```

Supported theme values:

- `auto`: follow the host Tailwind dark-mode strategy and the visitor system preference.
- `light`: render the widget with the light theme contract.
- `dark`: add a `dark` class to the widget root and render the dark Tailwind variant.

Supported positions:

- `bottom-right`
- `bottom-left`
- `bottom-center`
- `top-right`
- `top-left`
- `top-center`
- `center`
- `center-left`
- `center-right`

Short aliases are also accepted:

- `top` maps to `top-center`.
- `bottom` maps to `bottom-center`.
- `left` maps to `center-left`.
- `right` maps to `center-right`.

If your app compiles Tailwind and the default package classes are missing, include the package views in your Tailwind source scan.

Tailwind CSS v4:

```css
@source "../../vendor/mbs047/model-mind/resources/views/**/*.blade.php";
```

Tailwind CSS v3:

```js
export default {
    content: [
        './resources/views/**/*.blade.php',
        './vendor/mbs047/model-mind/resources/views/**/*.blade.php',
    ],
};
```

## Configuring Models

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

Model options:

- `enabled`: must be `true` for the model to be used.
- `label`: human-readable model name in the prompt context.
- `description`: short explanation of what this model means.
- `columns`: use `auto` for safe column discovery, or provide an explicit array.
- `include`: extra columns that should be included when safe.
- `exclude`: columns that should never be sent.
- `relations`: Eloquent relations to eager load with selected columns.
- `limit`: max records for this model before global security limits are applied.
- `order_by`: column and direction pairs used to sort records.
- `route_actions`: safe Laravel named-route actions that can become chat buttons.

## Named Route Actions

ModelMind can turn approved Laravel named routes into clickable chat buttons. This is safer than asking the AI to invent URLs because the package only resolves routes that you explicitly configure.

Per-model route action:

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

Global route action:

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

When a row has enough data to build a route action, ModelMind adds a route token to the enabled context:

```text
[[model_mind_route key="products.view" product="123"]]
```

The AI is instructed to copy only approved route tokens. The server then validates the key and parameter names, generates the URL with Laravel's `route()` helper, removes the token from the visible answer, and returns a button action.

Settings:

```env
MODEL_MIND_MAX_ACTIONS=5
MODEL_MIND_ROUTE_TOKEN=model_mind_route
```

Optional config:

```php
'actions' => [
    'max_actions' => 5,
    'route_token' => 'model_mind_route',
    'allow_label_override' => false,
],
```

Keep `allow_label_override` disabled unless you trust the model to choose button labels. With the default setting, labels always come from your config or model trait.

## Per-Model Overrides

Use the `HasModelMindContext` trait when a model needs package-specific behavior.

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mbs\ModelMind\Concerns\HasModelMindContext;

class Product extends Model
{
    use HasModelMindContext;

    public function modelMindLabel(): string
    {
        return 'Products';
    }

    public function modelMindDescription(): ?string
    {
        return 'Sellable catalog products visible to customers.';
    }

    public function modelMindHiddenColumns(): array
    {
        return ['cost', 'supplier_private_notes'];
    }

    public function modelMindRouteActions(): array
    {
        return [
            'products.view' => [
                'label' => 'View product',
                'route' => 'products.show',
                'parameters' => ['product' => 'id'],
            ],
        ];
    }

    public function modelMindContextQuery(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }
}
```

Trait methods:

- `modelMindLabel()`: override the display label.
- `modelMindDescription()`: describe the model for the assistant.
- `modelMindContextColumns()`: return `auto` or an explicit column array.
- `modelMindHiddenColumns()`: add package-specific hidden columns.
- `modelMindContextRelations()`: return relations to include.
- `modelMindRouteActions()`: define safe named-route actions for records of this model.
- `modelMindContextQuery()`: scope records before they enter context.

## Inspecting Context

Before enabling ModelMind in production, inspect the context that can be sent to the provider:

```bash
php artisan model-mind:inspect-context
php artisan model-mind:inspect-context --json
```

Use this command after every major config change.

## Security Controls

ModelMind is explicit and deny-first:

- It does not expose all application models automatically.
- It filters common sensitive columns such as passwords, tokens, secrets, private keys, recovery codes, bank fields, and identity fields.
- It respects model `$hidden` and `$visible`.
- It blocks encrypted and hashed casts by default.
- It strips HTML from model context values by default.
- It treats database values as data, not instructions.

Important settings:

```php
'security' => [
    'auto_discover_models' => false,
    'strip_html' => true,
    'field_character_limit' => 600,
    'max_rows_per_model' => 25,
    'max_context_characters' => 12000,
    'blocked_columns' => [
        'password',
        'remember_token',
        'api_token',
    ],
    'blocked_patterns' => [
        '/password/i',
        '/token/i',
        '/secret/i',
    ],
],
```

## Learning Memory

ModelMind can learn from assistant answers, liked answers, manually fed text, and configured snippets.

```env
MODEL_MIND_LEARNING_ENABLED=true
MODEL_MIND_LEARN_FROM_ASSISTANT_ANSWERS=true
MODEL_MIND_LEARN_FROM_LIKED_ANSWERS=true
MODEL_MIND_LEARN_FROM_FED_TEXTS=true
MODEL_MIND_LEARNING_MIN_CHARACTERS=40
MODEL_MIND_LEARNING_TEXT_CHARACTERS=1200
MODEL_MIND_LEARNING_CONTEXT_LIMIT=12
```

Feed text manually:

```bash
php artisan model-mind:learn "Warranty coverage lasts one year." --title="Warranty policy"
```

Configure reusable fed text:

```php
'learning' => [
    'fed_texts' => [
        [
            'title' => 'Support policy',
            'content' => 'Support replies happen within one business day.',
            'source' => 'manual',
        ],
    ],
],
```

Learning has its own sensitive-pattern filter so API keys, tokens, secrets, and passwords are not stored as memories.

## Feedback

When feedback is enabled, assistant messages can be marked:

- `Helpful`
- `Not helpful`

After a visitor selects one option, the selected button is highlighted and the opposite option is disabled. Helpful answers can be saved into learning memory when `from_liked_answers` is enabled.

## Database Tables

ModelMind uses one migration file and creates three tables:

- `model_mind_sessions`
- `model_mind_messages`
- `model_mind_memories`

Change the table prefix before running migrations:

```env
MODEL_MIND_TABLE_PREFIX=assistant_
```

With that setting, the tables become:

- `assistant_sessions`
- `assistant_messages`
- `assistant_memories`

## Customizing the Chat Modal

For a design-only change, publish the default views:

```bash
php artisan model-mind:install --views
```

Published files live in:

```text
resources/views/vendor/model-mind/components/modal.blade.php
resources/views/vendor/model-mind/components/styles.blade.php
resources/views/vendor/model-mind/components/scripts.blade.php
```

For a fully custom design, point the package to your own views:

```env
MODEL_MIND_MODAL_VIEW=components.ai.model-mind-modal
MODEL_MIND_STYLES_VIEW=components.ai.model-mind-styles
MODEL_MIND_SCRIPTS_VIEW=components.ai.model-mind-scripts
```

You can also override views directly from Blade:

```blade
@modelMindModal('components.ai.model-mind-modal')
@modelMindStyles('components.ai.model-mind-styles')
@modelMindScripts('components.ai.model-mind-scripts')
```

Or render everything with custom view names:

```blade
@modelMind([
    'modal' => 'components.ai.model-mind-modal',
    'styles' => 'components.ai.model-mind-styles',
    'scripts' => 'components.ai.model-mind-scripts',
])
```

See [CUSTOMIZING-THE-MODAL.md](CUSTOMIZING-THE-MODAL.md) for a complete custom-design guide and the required `data-model-mind-*` attributes.

## Custom Context Providers

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

## Custom AI Providers

The package resolves `Mbs\ModelMind\Contracts\ModelMindProvider`. Bind your own provider in an application service provider:

```php
use Mbs\ModelMind\Contracts\ModelMindProvider;

public function register(): void
{
    $this->app->bind(ModelMindProvider::class, App\Support\Ai\CustomModelMindProvider::class);
}
```

Your provider receives a `ModelMindRequestData` object and returns `ModelMindResponseData`.

## Routes

Default route prefix:

```env
MODEL_MIND_ROUTE_PREFIX=model-mind
MODEL_MIND_ROUTE_NAME=model-mind.
```

The package registers:

- `POST /model-mind/chat`
- `GET /model-mind/session`
- `POST /model-mind/messages/{message}/feedback`

The default middleware is:

```php
['web', 'throttle:model-mind']
```

## Performance

Good defaults for most apps:

```env
MODEL_MIND_CONTEXT_CACHE_SECONDS=600
MODEL_MIND_RECENT_MESSAGES=8
MODEL_MIND_BROWSER_MESSAGES=60
MODEL_MIND_MESSAGE_CHARACTERS=800
MODEL_MIND_SUMMARY_CHARACTERS=2000
MODEL_MIND_MAX_OUTPUT_TOKENS=450
MODEL_MIND_TIMEOUT=12
MODEL_MIND_CONNECT_TIMEOUT=3
```

For large applications:

- Keep per-model `limit` small.
- Prefer scoped public records with `modelMindContextQuery()`.
- Use custom context providers for summarized analytics instead of sending many raw rows.
- Run `model-mind:inspect-context` and check the resulting character size.

## Testing a Host Application

After installation in an app, test these paths:

```bash
php artisan model-mind:inspect-context
php artisan route:list --name=model-mind
php artisan test
```

In the browser:

- Open the chat.
- Ask a question from an enabled model.
- Refresh and confirm history restores.
- Mark an answer as `Helpful`.
- Check light and dark appearances.
- Confirm hidden or sensitive fields never appear.

## Package Development

For contributors working inside the package repository:

```bash
composer install
composer validate --strict
composer test
composer format
```

The package test suite uses Orchestra Testbench and SQLite in memory.
