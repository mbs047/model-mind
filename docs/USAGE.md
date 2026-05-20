# ModelMind Usage Guide

This page is the compact starting point for ModelMind. Each major feature now has its own documentation file so package users can jump directly to the part they need.

## Start Here

1. Install the package: [Installation](installation.md)
2. Copy a starting setup: [Examples](examples.md)
3. Choose a preset: [Presets](presets.md)
4. Configure a provider: [OpenAI Configuration](openai.md) or [Provider Drivers](provider-drivers.md)
5. Render the widget: [Blade Rendering](blade-rendering.md)
6. Configure starter prompts: [Default Questions](default-questions.md)
7. Enable application data: [Models and Context](models.md)
8. Inspect what the assistant can see: [Security Controls](security.md)

## Feature Guides

- [Installation](installation.md)
- [Examples](examples.md)
- [Presets](presets.md)
- [OpenAI Configuration](openai.md)
- [Blade Rendering](blade-rendering.md)
- [Default Questions](default-questions.md)
- [UI, Themes, and Positioning](ui-themes-and-positioning.md)
- [Models and Context](models.md)
- [Per-Model Overrides](per-model-overrides.md)
- [Question-Aware Retrieval](retrieval.md)
- [Named Route Actions](route-actions.md)
- [Source Citations](source-citations.md)
- [Authorization and User-Aware Context](authorization.md)
- [Security Controls](security.md)
- [Learning Memory](learning-memory.md)
- [Feedback](feedback.md)
- [Current Page Context](page-context.md)
- [Usage Analytics](analytics.md)
- [Events and Hooks](events-and-hooks.md)
- [Sessions](sessions.md)
- [Multilingual Answers](multilingual.md)
- [Streaming Responses](streaming.md)
- [Provider Drivers](provider-drivers.md)
- [Database Tables](database-tables.md)
- [Headless API](headless-api.md)
- [Public Assets](public-assets.md)
- [Customizing the Chat Modal](customizing-the-modal.md)
- [Custom Context Providers](context-providers.md)
- [Custom AI Providers](ai-providers.md)
- [Package Routes](package-routes.md)
- [Performance](performance.md)
- [Testing](testing.md)
- [Package Development](package-development.md)

## Quick Install

```bash
composer require mbs047/model-mind
php artisan model-mind:install
php artisan migrate
```

```env
OPENAI_API_KEY=sk-your-key
OPENAI_ORGANIZATION=org-your-organization-id
MODEL_MIND_MODEL=gpt-5-nano
```

```blade
@modelMind
```

Use `php artisan model-mind:preset --list` to see store, admin, support, docs, and CRM recommendations.

For repeated route buttons, configure `label_column` or `label_template` so actions can show record-specific labels such as `View Samsung Galaxy S24 Ultra`. For trusted answer debugging, keep source citations enabled and configure `source_label_column` or `source_label_template`.
