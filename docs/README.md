# ModelMind Documentation

ModelMind is a secure, model-aware AI chat assistant for Laravel applications. The docs are organized by feature so each part of the package has a focused guide.

## Getting Started

- [Installation](installation.md)
- [Examples](examples.md)
- [Presets](presets.md)
- [OpenAI Configuration](openai.md)
- [Blade Rendering](blade-rendering.md)
- [Default Questions](default-questions.md)
- [UI, Themes, and Positioning](ui-themes-and-positioning.md)

## Application Data

- [Models and Context](models.md)
- [Per-Model Overrides](per-model-overrides.md)
- [Question-Aware Retrieval](retrieval.md)
- [Named Route Actions](route-actions.md)
- [Source Citations](source-citations.md)
- [Authorization and User-Aware Context](authorization.md)
- [Security Controls](security.md)
- [Custom Context Providers](context-providers.md)

## Assistant Behavior

- [Learning Memory](learning-memory.md)
- [Feedback](feedback.md)
- [Usage Analytics](analytics.md)
- [Events and Hooks](events-and-hooks.md)
- [Sessions](sessions.md)
- [Multilingual Answers](multilingual.md)
- [Streaming Responses](streaming.md)
- [Provider Drivers](provider-drivers.md)
- [Custom AI Providers](ai-providers.md)
- [Performance](performance.md)

## Package Surface

- [Database Tables](database-tables.md)
- [Package Routes](package-routes.md)
- [Headless API](headless-api.md)
- [Public Assets](public-assets.md)
- [Customizing the Chat Modal](customizing-the-modal.md)
- [Testing](testing.md)
- [Package Development](package-development.md)

## Short Path

```bash
composer require mbs047/model-mind
php artisan model-mind:install
php artisan migrate
```

```blade
@modelMind
```

Then enable at least one model in `config/model-mind.php` and run:

```bash
php artisan model-mind:inspect-context
```

For copyable configurations, see [Examples](examples.md).
