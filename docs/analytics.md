# Usage Analytics

ModelMind can store operational analytics so host applications can build dashboards around assistant usage, quality, and reliability.

## What Is Tracked

When analytics are enabled, ModelMind records events for:

- Completed answers with latency, provider, model, token usage, action count, and citation count.
- Failed answers with provider, configured model, latency, error class, and safe error message.
- Feedback submissions with `liked` or `disliked` status.
- Route/action clicks from the default Blade widget and custom clients.

Analytics are stored in the `model_mind_events` table, or the same table name under your configured table prefix.

## Configuration

```env
MODEL_MIND_ANALYTICS_ENABLED=true
MODEL_MIND_ANALYTICS_SUMMARY_DAYS=7
```

```php
'analytics' => [
    'enabled' => true,
    'summary_days' => 7,
],
```

The analytics writer is fail-safe. If an upgraded application has not added the events table yet, chat still works and analytics writes are skipped.

## Command

Use the built-in command for quick dashboard data:

```bash
php artisan model-mind:analytics
php artisan model-mind:analytics --days=30
php artisan model-mind:analytics --json
```

The JSON output includes totals, provider/model rollups, feedback rate, action-click summaries, token totals, and error summaries.

## Laravel Event

Every stored analytics row dispatches:

```php
Mbs\ModelMind\Events\ModelMindAnalyticsRecorded
```

Listen to this event when you want to mirror ModelMind analytics into your own warehouse, dashboard table, or external observability tool.

```php
use Mbs\ModelMind\Events\ModelMindAnalyticsRecorded;

Event::listen(ModelMindAnalyticsRecorded::class, function (ModelMindAnalyticsRecorded $event): void {
    $analyticsEvent = $event->event;
});
```

## Eloquent Access

```php
use Mbs\ModelMind\Models\ModelMindEvent;

$events = ModelMindEvent::query()
    ->latest('occurred_at')
    ->limit(100)
    ->get();
```

Useful event types:

- `chat.completed`
- `chat.failed`
- `feedback.submitted`
- `action.clicked`

## Action Clicks

The default widget posts action clicks to:

```http
POST /model-mind/actions/click
```

Headless clients can use:

```http
POST /api/model-mind/actions/click
```

Payload:

```json
{
    "session_id": "9c575b06-8c3f-4b62-82e1-3c50859e6fd8",
    "message_id": "d9d54b44-14d7-4779-b91d-96abcc0dd5ec",
    "label": "View product",
    "url": "https://example.test/products/1",
    "kind": "route",
    "source": "action",
    "index": 0
}
```

Use `source: "citation"` when the click came from a source citation button.
