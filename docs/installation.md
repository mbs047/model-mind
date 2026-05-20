# Installation

Install ModelMind with Composer:

```bash
composer require mbs047/model-mind
```

Publish the config, migration, and public CSS/JavaScript assets:

```bash
php artisan model-mind:install
```

Run the migration:

```bash
php artisan migrate
```

To also publish the Blade modal view for customization:

```bash
php artisan model-mind:install --views
```

To republish the public CSS and JavaScript assets later:

```bash
php artisan model-mind:publish-assets
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
- A provider credential when using a hosted provider driver such as OpenAI, Anthropic, or Gemini.

## Next Steps

- Configure [OpenAI](openai.md) or another [Provider Driver](provider-drivers.md).
- Render the [Blade widget](blade-rendering.md).
- Enable [models and context](models.md).
