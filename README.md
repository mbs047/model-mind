# ModelMind

A secure, configurable, model-aware AI chat assistant for Laravel applications.

ModelMind adds a reusable Blade/Tailwind chat modal, persisted conversation memory, configurable Eloquent model context, safe column auto-discovery, per-model overrides, and an OpenAI Responses API provider behind an interface.

## Features

- Branded `ModelMind` chat modal for Laravel Blade apps.
- Blade directives: `@modelMind`, `@modelMindModal`, `@modelMindScripts`, `@modelMindStyles`.
- Self-contained browser script. Alpine is not required.
- Anonymous Blade components such as `<x-model-mind::modal />`.
- Persisted chat sessions, messages, server history restore, and feedback.
- Config-driven Eloquent model context.
- `HasModelMindContext` trait for per-model labels, query scopes, hidden columns, and custom context output.
- Sensitive-column filtering for passwords, tokens, secrets, private keys, recovery codes, and similar fields.
- Context inspection command before production use.
- Publishable config, migrations, and views.

## Installation

```bash
composer require mbs047/model-mind

php artisan model-mind:install
php artisan migrate
```

Configure OpenAI:

```env
OPENAI_API_KEY=sk-your-key
OPENAI_ORGANIZATION=org-your-organization-id
MODEL_MIND_MODEL=gpt-5-nano
```

You may also use `MODEL_MIND_OPENAI_API_KEY` and `MODEL_MIND_OPENAI_ORGANIZATION` when this package should use different credentials than the rest of the app.

By default, the modal ships with the `MBS` brand mark. Override it per app with `MODEL_MIND_BRAND_MARK`.

The widget position is configurable:

```env
MODEL_MIND_POSITION=bottom-right
MODEL_MIND_WIDTH=25rem
MODEL_MIND_OFFSET=1.25rem
MODEL_MIND_Z_INDEX=9999
```

Supported positions: `bottom-right`, `bottom-left`, `bottom-center`, `top-right`, `top-left`, `top-center`, `center`, `center-left`, `center-right`. Short aliases are also accepted: `top`, `bottom`, `left`, and `right`.

Database tables use the `model_mind_` prefix by default. Change it before running migrations if your app needs a different namespace:

```env
MODEL_MIND_TABLE_PREFIX=assistant_
```

If installing directly from GitHub before Packagist registration, add a Composer repository:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/mbs047/model-mind"
        }
    ]
}
```

## Usage

Add the modal and scripts to a Blade layout:

```blade
@modelMindStyles
@modelMindModal
@modelMindScripts
```

Or render everything at once:

```blade
@modelMind
```

## Configure Models

Only configured models are exposed to the assistant.

```php
'models' => [
    App\Models\Product::class => [
        'enabled' => true,
        'label' => 'Products',
        'description' => 'Public product catalog.',
        'columns' => 'auto',
        'include' => ['name', 'slug', 'summary', 'price'],
        'exclude' => ['internal_notes'],
        'relations' => ['category:id,name'],
        'limit' => 50,
        'order_by' => ['updated_at' => 'desc'],
    ],
],
```

## Per-Model Overrides

Use the trait when a model needs package-specific behavior.

```php
use Illuminate\Database\Eloquent\Builder;
use Mbs\ModelMind\Concerns\HasModelMindContext;

class Product extends Model
{
    use HasModelMindContext;

    public function modelMindLabel(): string
    {
        return 'Products';
    }

    public function modelMindHiddenColumns(): array
    {
        return ['cost', 'internal_notes'];
    }

    public function modelMindContextQuery(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }
}
```

## Inspect Context

Before enabling a model in production, inspect the exact context that can be sent to the provider:

```bash
php artisan model-mind:inspect-context
php artisan model-mind:inspect-context --json
```

## Learning

ModelMind can learn from assistant answers, liked answers, manually fed text, and configured text snippets. Learned knowledge is stored in the package memory table and is reused in future prompts.

Feed text from the command line:

```bash
php artisan model-mind:learn "Warranty coverage lasts one year." --title="Warranty policy"
```

Or configure reusable fed text in `config/model-mind.php`:

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

Useful performance defaults:

```env
MODEL_MIND_MAX_OUTPUT_TOKENS=450
MODEL_MIND_CONTEXT_CACHE_SECONDS=600
MODEL_MIND_RETRY_WHEN_TRUNCATED=false
```

## Security Model

The package is explicit by design:

- It does not expose every model automatically.
- It blocks common sensitive columns and suspicious names by default.
- It respects model `$hidden` and `$visible`.
- It strips HTML from context values by default.
- It treats database content as data, not instructions.

Review `config/model-mind.php` before production use.
