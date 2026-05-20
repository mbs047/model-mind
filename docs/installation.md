# Installation

Install ModelMind with Composer:

```bash
composer require mbs047/model-mind
```

Publish the config and migration:

```bash
php artisan model-mind:install
```

Run the migration:

```bash
php artisan migrate
```

To also publish the Blade views for customization:

```bash
php artisan model-mind:install --views
```

To publish the public CSS and JavaScript assets:

```bash
php artisan model-mind:publish-assets
```

Or include them during install:

```bash
php artisan model-mind:install --assets
```

You can publish individual groups manually:

```bash
php artisan vendor:publish --tag=model-mind-config
php artisan vendor:publish --tag=model-mind-migrations
php artisan vendor:publish --tag=model-mind-views
php artisan vendor:publish --tag=model-mind-assets
```

## Requirements

- PHP 8.3 or newer.
- Laravel 11, 12, or 13.
- A database supported by Laravel migrations.
- Tailwind CSS in the host application for the default design.
- An OpenAI API key when using the built-in OpenAI provider.

## Next Steps

- Configure [OpenAI](openai.md).
- Render the [Blade widget](blade-rendering.md).
- Enable [models and context](models.md).
