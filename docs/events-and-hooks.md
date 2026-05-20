# Events and Hooks

ModelMind dispatches Laravel events at the main extension points so applications can log activity, start jobs, update dashboards, notify teams, or sync package activity into their own systems.

Register listeners in your application service provider:

```php
use Illuminate\Support\Facades\Event;
use Mbs\ModelMind\Events\AnswerGenerated;

Event::listen(AnswerGenerated::class, function (AnswerGenerated $event): void {
    // Mirror the generated answer into your own audit log, warehouse, or dashboard.
});
```

## Available Events

### `MessageSent`

Dispatched after a visitor question is stored as a `ModelMindMessage`.

```php
use Mbs\ModelMind\Events\MessageSent;

Event::listen(MessageSent::class, function (MessageSent $event): void {
    $session = $event->session;
    $message = $event->message;
    $question = $event->question;
    $transport = $event->context['transport'] ?? null;
    $pageContext = $event->context['page_context'] ?? [];
});
```

### `AnswerGenerated`

Dispatched after the provider answer has been cleaned, route actions and citations have been resolved, and the assistant message has been stored.

```php
use Mbs\ModelMind\Events\AnswerGenerated;

Event::listen(AnswerGenerated::class, function (AnswerGenerated $event): void {
    $message = $event->message;
    $answer = $event->answer;
    $actions = $event->actions;
    $citations = $event->citations;
    $provider = $event->providerMetadata['provider'] ?? null;
    $model = $event->providerMetadata['model'] ?? null;
    $latencyMs = $event->latencyMs;
});
```

### `FeedbackSubmitted`

Dispatched after feedback is saved on an assistant message.

```php
use Mbs\ModelMind\Events\FeedbackSubmitted;

Event::listen(FeedbackSubmitted::class, function (FeedbackSubmitted $event): void {
    $message = $event->message;
    $feedback = $event->feedback; // liked or disliked
    $note = $event->note;
});
```

### `ActionResolved`

Dispatched whenever a configured route action is converted into a safe URL button.

```php
use Mbs\ModelMind\Events\ActionResolved;

Event::listen(ActionResolved::class, function (ActionResolved $event): void {
    $key = $event->key;
    $action = $event->action; // label, url, kind
    $parameters = $event->parameters;
    $definition = $event->definition;
});
```

This fires for explicit route action tokens, inferred route actions, and citation source actions.

### `MemoryLearned`

Dispatched after ModelMind stores or refreshes learned memory.

```php
use Mbs\ModelMind\Events\MemoryLearned;

Event::listen(MemoryLearned::class, function (MemoryLearned $event): void {
    $memory = $event->memory;
    $created = $event->created;
    $metadata = $event->metadata;
});
```

This can come from assistant answers, liked feedback, manually fed text, or any custom code that calls `LearningRepository::remember()`.

### `ModelMindAnalyticsRecorded`

Dispatched after an analytics event row is stored.

```php
use Mbs\ModelMind\Events\ModelMindAnalyticsRecorded;

Event::listen(ModelMindAnalyticsRecorded::class, function (ModelMindAnalyticsRecorded $event): void {
    $analyticsEvent = $event->event;
});
```

See [Usage Analytics](analytics.md) for the analytics event table and summary command.

## Queueing Listeners

Use normal Laravel queued listeners when your hook does work outside the request path:

```php
namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Mbs\ModelMind\Events\AnswerGenerated;

class MirrorModelMindAnswer implements ShouldQueue
{
    public function handle(AnswerGenerated $event): void
    {
        // Send to a warehouse, CRM, or internal audit table.
    }
}
```

Keep listeners idempotent when they write to external systems. Chat requests can be retried by browsers, mobile clients, proxies, or queue workers.
