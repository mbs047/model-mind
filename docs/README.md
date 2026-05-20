# ModelMind Documentation

ModelMind is a secure, model-aware AI chat assistant for Laravel applications. The docs are organized by feature so each part of the package has a focused guide.

## Getting Started

- [Installation](installation.md)
- [OpenAI Configuration](openai.md)
- [Blade Rendering](blade-rendering.md)
- [UI, Themes, and Positioning](ui-themes-and-positioning.md)

## Application Data

- [Models and Context](models.md)
- [Per-Model Overrides](per-model-overrides.md)
- [Named Route Actions](route-actions.md)
- [Security Controls](security.md)
- [Custom Context Providers](context-providers.md)

## Assistant Behavior

- [Learning Memory](learning-memory.md)
- [Feedback](feedback.md)
- [Custom AI Providers](ai-providers.md)
- [Performance](performance.md)

## Package Surface

- [Database Tables](database-tables.md)
- [Package Routes](package-routes.md)
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
