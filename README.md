<p align="center">
    <img src="art/model-mind-banner.png" alt="ModelMind - AI assistant for your Laravel application">
</p>

# ModelMind

[![CI](https://github.com/mbs047/model-mind/actions/workflows/ci.yml/badge.svg)](https://github.com/mbs047/model-mind/actions/workflows/ci.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/mbs047/model-mind.svg)](https://packagist.org/packages/mbs047/model-mind)
[![License](https://img.shields.io/github/license/mbs047/model-mind.svg)](LICENSE.md)

ModelMind is a secure, configurable, model-aware AI chat assistant for Laravel applications.

It gives any Laravel app a reusable Blade/Tailwind chat modal, persisted conversation history, configurable Eloquent model context, safe column auto-discovery, learning memory, feedback, and an OpenAI provider behind a clean package interface.

## Features

- Drop-in Blade chat modal with Tailwind-friendly styles.
- `@modelMind`, `@modelMindModal`, `@modelMindStyles`, and `@modelMindScripts` directives.
- Configurable widget position, width, offset, z-index, labels, prompt tone, and brand mark.
- Model-aware answers from only the Eloquent models you explicitly enable.
- Auto-discovered columns with sensitive field filtering.
- Per-model control through the `HasModelMindContext` trait.
- Persisted chat sessions, message history, user feedback, and learned knowledge.
- Configurable table prefix for clean package ownership.
- OpenAI Responses API support with package-specific API key and organization options.

## Quick Start

Install the package:

```bash
composer require mbs047/model-mind
```

Publish the package assets and run migrations:

```bash
php artisan model-mind:install
php artisan migrate
```

Add your OpenAI credentials:

```env
OPENAI_API_KEY=sk-your-key
OPENAI_ORGANIZATION=org-your-organization-id
MODEL_MIND_MODEL=gpt-5-nano
```

Render ModelMind in your Blade layout:

```blade
@modelMindStyles
@modelMindModal
@modelMindScripts
```

Or use the one-line directive:

```blade
@modelMind
```

Enable the models the assistant is allowed to know about in `config/model-mind.php`:

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

Inspect the exact context before using it in production:

```bash
php artisan model-mind:inspect-context
```

That is the whole first path: install, configure credentials, render the widget, enable safe model context, inspect it, and ask questions from your app.

## Common Options

```env
MODEL_MIND_BRAND_MARK=MBS
MODEL_MIND_THEME=auto
MODEL_MIND_POSITION=bottom-right
MODEL_MIND_WIDTH=25rem
MODEL_MIND_OFFSET=1.25rem
MODEL_MIND_Z_INDEX=9999
MODEL_MIND_TABLE_PREFIX=model_mind_
MODEL_MIND_CONTEXT_CACHE_SECONDS=600
MODEL_MIND_MAX_OUTPUT_TOKENS=450
```

Supported widget positions are `bottom-right`, `bottom-left`, `bottom-center`, `top-right`, `top-left`, `top-center`, `center`, `center-left`, and `center-right`. Short aliases are accepted for `top`, `bottom`, `left`, and `right`.

## Full Documentation

Read the complete usage guide in [docs/USAGE.md](docs/USAGE.md). It covers installation, Blade directives, model configuration, security rules, learning memory, feedback, table prefixes, positions, OpenAI settings, custom providers, custom context providers, actions, testing, and release practices.

For custom UI work, read [docs/CUSTOMIZING-THE-MODAL.md](docs/CUSTOMIZING-THE-MODAL.md). You can publish the default Blade views, or point ModelMind to a completely new modal, styles, and script from config.

## Security

ModelMind is explicit by design. It does not expose every model automatically, filters common sensitive columns, respects model `$hidden` and `$visible`, strips HTML from context values by default, and treats database content as data rather than instructions.

Review [SECURITY.md](SECURITY.md) before reporting vulnerabilities or enabling sensitive production data.

## Contributing

Pull requests are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md), run the test suite, and keep the package behavior secure by default.

```bash
composer install
composer test
composer format
```

## License

ModelMind is open-sourced software licensed under the [MIT license](LICENSE.md).
