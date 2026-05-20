# Public Assets

ModelMind can publish its default CSS and JavaScript into `public/vendor/model-mind`. This is useful when you want browser-cacheable files, CDN handling, or a custom design that keeps CSS and JS out of Blade views.

## Publish Assets

```bash
php artisan model-mind:publish-assets
```

Assets are included during package install:

```bash
php artisan model-mind:install
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
MODEL_MIND_STYLES_ASSET=vendor/model-mind/model-mind.css
MODEL_MIND_SCRIPTS_ASSET=vendor/model-mind/model-mind.js
```

The standard directives always render public asset tags:

```blade
@modelMindStyles
@modelMindScripts
```

The modal markup still comes from Blade because it contains Laravel routes, CSRF data, labels, theme, and runtime config. If you do not want Blade markup at all, use the [Headless API](headless-api.md) and build the chat UI in your client application.

## Custom Assets

Create your own files:

```text
public/vendor/model-mind/custom.css
public/vendor/model-mind/custom.js
```

Then point ModelMind to them:

```env
MODEL_MIND_STYLES_ASSET=vendor/model-mind/custom.css
MODEL_MIND_SCRIPTS_ASSET=vendor/model-mind/custom.js
```

Custom scripts must keep the same endpoint behavior and should read the JSON config from `data-model-mind-config`. See [Customizing the Chat Modal](customizing-the-modal.md) for the markup contract.
