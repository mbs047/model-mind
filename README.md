# MBS Laravel AI Chat

A secure, configurable, model-aware AI chat assistant for Laravel applications.

MBS Laravel AI Chat adds a reusable Blade/Tailwind chat modal, persisted conversation memory, configurable Eloquent model context, safe column auto-discovery, per-model overrides, and an OpenAI Responses API provider behind an interface.

## Features

- Branded `MBS Assistant` chat modal for Laravel Blade apps.
- Blade directives: `@mbsChat`, `@mbsChatModal`, `@mbsChatScripts`, `@mbsChatStyles`.
- Anonymous Blade components such as `<x-mbs-ai-chat::modal />`.
- Persisted chat sessions, messages, server history restore, and feedback.
- Config-driven Eloquent model context.
- `HasAiChatContext` trait for per-model labels, query scopes, hidden columns, and custom context output.
- Sensitive-column filtering for passwords, tokens, secrets, private keys, recovery codes, and similar fields.
- Context inspection command before production use.
- Publishable config, migrations, and views.

## Installation

```bash
composer require mbs/laravel-ai-chat

php artisan mbs-ai-chat:install
php artisan migrate
```

If installing directly from GitHub before Packagist registration, add a Composer repository:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/mbs047/mbs-laravel-ai-chat"
        }
    ]
}
```

## Usage

Add the modal and scripts to a Blade layout:

```blade
@mbsChatStyles
@mbsChatModal
@mbsChatScripts
```

Or render everything at once:

```blade
@mbsChat
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
use Mbs\LaravelAiChat\Concerns\HasAiChatContext;

class Product extends Model
{
    use HasAiChatContext;

    public function aiChatLabel(): string
    {
        return 'Products';
    }

    public function aiChatHiddenColumns(): array
    {
        return ['cost', 'internal_notes'];
    }

    public function aiChatContextQuery(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }
}
```

## Inspect Context

Before enabling a model in production, inspect the exact context that can be sent to the provider:

```bash
php artisan mbs-ai-chat:inspect-context
php artisan mbs-ai-chat:inspect-context --json
```

## Security Model

The package is explicit by design:

- It does not expose every model automatically.
- It blocks common sensitive columns and suspicious names by default.
- It respects model `$hidden` and `$visible`.
- It strips HTML from context values by default.
- It treats database content as data, not instructions.

Review `config/mbs-ai-chat.php` before production use.
