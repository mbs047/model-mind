# Current Page Context

ModelMind can answer questions about the page the visitor is currently viewing, such as:

- "Summarize this page."
- "What do you think about this product?"
- "Explain the selected text."
- "What details are visible on this record page?"

The default Blade widget sends a small sanitized page snapshot with each message. Headless clients can send the same `page_context` payload.

## Toggle

Current page context is enabled by default.

```env
MODEL_MIND_PAGE_CONTEXT_ENABLED=true
```

```php
'features' => [
    'page_context' => true,
],

'page_context' => [
    'enabled' => true,
],
```

Set it to `false` when the page may contain browser-only sensitive content that should never leave the visitor's browser.

## What Is Sent

The default widget can send:

- Current URL
- Page title
- Meta description
- Browser-selected text
- `h1`, `h2`, and `h3` headings
- Visible page text from configured selectors
- Browser/page locale

The server re-sanitizes and truncates this payload before it reaches the provider.

## Configure Selectors

Use selectors to focus the snapshot on the useful page area.

```php
'page_context' => [
    'enabled' => true,
    'max_content_characters' => 6000,
    'max_selection_characters' => 2000,
    'selectors' => [
        '[data-model-mind-page-context]',
        'main',
        'article',
    ],
    'exclude_selectors' => [
        '[data-model-mind-widget]',
        '.model-mind-widget',
        'header',
        'nav',
        'footer',
        'form',
        'script',
        'style',
    ],
],
```

For precise control, wrap the useful part of a page:

```blade
<main data-model-mind-page-context>
    <h1>{{ $product->name }}</h1>
    <p>{{ $product->description }}</p>
</main>
```

## Headless Payload

Custom clients can send page context with `chat` or `stream` requests:

```json
{
    "question": "Summarize this product page",
    "page_context": {
        "url": "https://example.test/products/1",
        "title": "Samsung Galaxy S24 Ultra",
        "description": "Product page for the Samsung Galaxy S24 Ultra.",
        "headings": ["Samsung Galaxy S24 Ultra", "Specifications"],
        "selection": "256GB storage",
        "content": "Large-screen Android flagship with AI tools and advanced cameras.",
        "locale": "en"
    }
}
```

## Safety Notes

Page context is treated as untrusted page content, not instructions. ModelMind can use it for current-page questions, but enabled application data remains the stronger source when both are available.

Do not add sensitive elements to your configured selectors. Prefer dedicated wrappers such as `data-model-mind-page-context` around the page content you actually want the assistant to inspect.
