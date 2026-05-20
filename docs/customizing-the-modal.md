# Customizing the ModelMind Chat Modal

ModelMind gives you two customization paths:

- Publish and edit the default package views.
- Create a completely new Blade design and point the package to it.

Use the first path for small visual changes. Use the second path when the assistant should match a product design system exactly.

## Publish the Default Views

```bash
php artisan model-mind:install --views
```

This publishes:

```text
resources/views/vendor/model-mind/components/modal.blade.php
resources/views/vendor/model-mind/components/styles.blade.php
resources/views/vendor/model-mind/components/scripts.blade.php
```

Laravel automatically prefers published package views over vendor package views, so the standard directives continue to work:

```blade
@modelMindStyles
@modelMindModal
@modelMindScripts
```

## Create a New Design

Create your modal Blade file and public asset files:

```text
resources/views/components/ai/model-mind-modal.blade.php
public/vendor/model-mind/model-mind.css
public/vendor/model-mind/model-mind.js
```

Publish the package assets first if you want a starting point:

```bash
php artisan model-mind:publish-assets
```

Or publish only the asset group with Laravel's native command:

```bash
php artisan vendor:publish --tag=model-mind-assets
```

See [Public Assets](public-assets.md) for the standalone asset guide.

Then update `config/model-mind.php` so only the modal markup is a custom Blade view and the styles/scripts come from public assets:

```php
'views' => [
    'modal' => env('MODEL_MIND_MODAL_VIEW', 'components.ai.model-mind-modal'),
    'styles' => env('MODEL_MIND_STYLES_VIEW', 'model-mind::components.styles'),
    'scripts' => env('MODEL_MIND_SCRIPTS_VIEW', 'model-mind::components.scripts'),
],

'assets' => [
    'use_public' => true,
    'styles_path' => 'vendor/model-mind/model-mind.css',
    'scripts_path' => 'vendor/model-mind/model-mind.js',
],
```

Or use environment variables:

```env
MODEL_MIND_MODAL_VIEW=components.ai.model-mind-modal
MODEL_MIND_USE_PUBLIC_ASSETS=true
MODEL_MIND_STYLES_ASSET=vendor/model-mind/model-mind.css
MODEL_MIND_SCRIPTS_ASSET=vendor/model-mind/model-mind.js
```

## Override Views in Blade

For one layout only:

```blade
@modelMindModal('components.ai.model-mind-modal')
```

Or pass the modal view name while leaving styles and scripts on the configured public assets:

```blade
@modelMind([
    'modal' => 'components.ai.model-mind-modal',
])
```

You can pass data to custom views:

```blade
@modelMind([
    'data' => [
        'surface' => 'glass',
        'accent' => 'emerald',
    ],
])
```

For one part:

```blade
@modelMindModal([
    'view' => 'components.ai.model-mind-modal',
    'data' => [
        'surface' => 'glass',
    ],
])
```

## Required Markup Contract

The package script finds elements by `data-model-mind-*` attributes. A custom modal must keep these attributes unless you also replace the script.

Minimum structure:

```blade
@php
    $assistant = config('model-mind.assistant');
    $modelMindConfig = [
        'endpoint' => route(config('model-mind.routes.name', 'model-mind.').'chat'),
        'sessionEndpoint' => route(config('model-mind.routes.name', 'model-mind.').'session'),
        'feedbackEndpoint' => url(config('model-mind.routes.prefix', 'model-mind').'/messages'),
        'csrfToken' => csrf_token(),
        'initialMessage' => $assistant['initial_message'] ?? null,
        'quickQuestions' => $assistant['default_questions'] ?? $assistant['quick_questions'] ?? [],
        'fallbackAnswer' => $assistant['fallback_answer'] ?? null,
        'storageKey' => config('model-mind.ui.storage_key', 'model-mind-state'),
        'browserMessages' => (int) config('model-mind.memory.browser_messages', 60),
        'historyMessages' => (int) config('model-mind.memory.recent_messages', 12),
        'sessionLifetimeMinutes' => (int) config('model-mind.memory.session_lifetime_minutes', 120),
        'feedbackEnabled' => (bool) config('model-mind.features.feedback', true),
        'theme' => config('model-mind.ui.theme', 'auto'),
    ];
@endphp

<div
    class="model-mind-widget"
    data-model-mind-widget
    data-model-mind-position="{{ config('model-mind.ui.position', 'bottom-right') }}"
    data-model-mind-theme="{{ config('model-mind.ui.theme', 'auto') }}"
>
    <script type="application/json" data-model-mind-config>
        {!! json_encode($modelMindConfig, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) !!}
    </script>

    <section data-model-mind-panel hidden>
        <div data-model-mind-messages></div>

        <div data-model-mind-quick-questions></div>

        <form data-model-mind-form>
            <textarea data-model-mind-draft></textarea>
            <button type="submit" data-model-mind-submit disabled>Send</button>
        </form>

        <p data-model-mind-failure hidden></p>
    </section>

    <button type="button" data-model-mind-toggle>
        Ask {{ $assistant['name'] ?? 'ModelMind' }}
    </button>
</div>
```

Optional attribute:

```blade
<button type="button" data-model-mind-close>Close</button>
```

## Required Attributes

- `data-model-mind-widget`: root element initialized by the package script.
- `data-model-mind-config`: JSON config script inside the widget.
- `data-model-mind-panel`: chat panel shown and hidden by the script.
- `data-model-mind-messages`: message list container.
- `data-model-mind-quick-questions`: quick question button container.
- `data-model-mind-form`: form submitted by the visitor.
- `data-model-mind-draft`: textarea or input for the visitor question.
- `data-model-mind-submit`: submit button.
- `data-model-mind-toggle`: launcher button.
- `data-model-mind-failure`: validation or network error target.
- `data-model-mind-close`: optional close button.

## Tailwind Light and Dark Theme

The default design uses Tailwind `dark:` variants and a root theme attribute.

```env
MODEL_MIND_THEME=auto
```

Supported values:

- `auto`: follows the host app dark-mode strategy.
- `light`: keeps the widget on the light theme contract.
- `dark`: adds the `dark` class to the widget root.

For a custom Tailwind design, keep the root class and attribute:

```blade
<div
    class="model-mind-widget {{ config('model-mind.ui.theme') === 'dark' ? 'dark' : '' }}"
    data-model-mind-widget
    data-model-mind-theme="{{ config('model-mind.ui.theme', 'auto') }}"
>
```

Then use standard Tailwind variants:

```blade
<section class="border border-slate-200 bg-white text-slate-950 shadow-xl dark:border-white/10 dark:bg-slate-950 dark:text-white">
    ...
</section>
```

If Tailwind does not generate package classes, include the package or custom view paths in your Tailwind source scan.

## Replacing Only the Public Assets

Keep the package modal, but use your own CSS file:

```env
MODEL_MIND_USE_PUBLIC_ASSETS=true
MODEL_MIND_STYLES_ASSET=vendor/model-mind/custom.css
```

Keep the package modal, but use your own JavaScript file:

```env
MODEL_MIND_USE_PUBLIC_ASSETS=true
MODEL_MIND_SCRIPTS_ASSET=vendor/model-mind/custom.js
```

## Replacing the Script

Only replace the script when you need a different client-side behavior, such as streaming, custom animations, or a design-system state manager.

Use `MODEL_MIND_SCRIPTS_ASSET` for a public file. Your script should still post to the configured endpoints:

- `POST` to `endpoint` for questions.
- `GET` to `sessionEndpoint` for history restore.
- `POST` to `${feedbackEndpoint}/${messageId}/feedback` for feedback.

Assistant responses can include actions returned by the server. For named-route actions, the server resolves the configured Laravel route and returns the final URL in the same `actions` payload as normal links:

```json
{
    "label": "View product",
    "url": "https://example.test/products/123",
    "kind": "route"
}
```

Custom scripts should render all action kinds safely and should not execute raw action content as HTML.

## Design Checklist

- Keep the launcher visible and reachable on mobile.
- Keep the panel within the viewport at every supported position.
- Preserve keyboard submit behavior.
- Use visible focus styles.
- Keep text contrast accessible in light and dark modes.
- Keep feedback labels readable: `Helpful` and `Not helpful`.
- Do not expose extra data in the Blade view.
- Run `php artisan model-mind:inspect-context` after model config changes.

## Testing a Custom Design

After changing the modal:

```bash
php artisan route:list --name=model-mind
php artisan model-mind:inspect-context
php artisan test
```

Manual browser checks:

- Open and close the widget.
- Submit a question.
- Confirm the submit button disables while empty.
- Confirm history restores after refresh.
- Select `Helpful` or `Not helpful`.
- Check `MODEL_MIND_THEME=light`, `MODEL_MIND_THEME=dark`, and `MODEL_MIND_THEME=auto`.
- Check all configured positions.
