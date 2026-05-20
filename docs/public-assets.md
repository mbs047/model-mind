# Public Assets

ModelMind can publish its default CSS and JavaScript into `public/vendor/model-mind`. This is useful when you want browser-cacheable files, CDN handling, or a custom design that keeps CSS and JS out of Blade views.

## Publish Assets

```bash
php artisan model-mind:publish-assets
```

Or include assets during package install:

```bash
php artisan model-mind:install --assets
```

You can also use Laravel's publish command directly:

```bash
php artisan vendor:publish --tag=model-mind-assets
```

This creates:

```text
public/vendor/model-mind/model-mind.css
public/vendor/model-mind/model-mind.js
```

## Use Published Assets

```env
MODEL_MIND_USE_PUBLIC_ASSETS=true
MODEL_MIND_STYLES_ASSET=vendor/model-mind/model-mind.css
MODEL_MIND_SCRIPTS_ASSET=vendor/model-mind/model-mind.js
```

When `MODEL_MIND_USE_PUBLIC_ASSETS=true`, the standard directives render public asset tags:

```blade
@modelMindStyles
@modelMindScripts
```

The modal markup still comes from Blade because it contains Laravel routes, CSRF data, labels, theme, and runtime config.

## Custom Assets

Create your own files:

```text
public/vendor/model-mind/custom.css
public/vendor/model-mind/custom.js
```

Then point ModelMind to them:

```env
MODEL_MIND_USE_PUBLIC_ASSETS=true
MODEL_MIND_STYLES_ASSET=vendor/model-mind/custom.css
MODEL_MIND_SCRIPTS_ASSET=vendor/model-mind/custom.js
```

Custom scripts must keep the same endpoint behavior and should read the JSON config from `data-model-mind-config`. See [Customizing the Chat Modal](customizing-the-modal.md) for the markup contract.
