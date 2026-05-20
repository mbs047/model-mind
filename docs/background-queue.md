# Background Queue

ModelMind keeps chat responses fast by moving non-critical work out of the request path:

- Conversation summary compaction.
- Learning from assistant answers and liked answers.
- Usage analytics writes for completions, failures, feedback, and action clicks.

## Modes

```env
MODEL_MIND_BACKGROUND_MODE=after_response
MODEL_MIND_BACKGROUND_CONNECTION=
MODEL_MIND_BACKGROUND_QUEUE=model-mind
MODEL_MIND_BACKGROUND_AFTER_COMMIT=true
```

Supported modes:

- `after_response`: default. Laravel runs the jobs after the HTTP response is sent. This is fast and does not require a queue worker.
- `queue`: dispatch jobs to your configured queue connection. Use this for high-traffic apps.
- `sync`: run background work immediately. Useful for local debugging and tests.

## Real Queue Worker

For production queue processing:

```env
QUEUE_CONNECTION=database
MODEL_MIND_BACKGROUND_MODE=queue
MODEL_MIND_BACKGROUND_CONNECTION=database
MODEL_MIND_BACKGROUND_QUEUE=model-mind
```

Then run a worker:

```bash
php artisan queue:work --queue=model-mind,default
```

If your app wraps chat writes in database transactions, keep `MODEL_MIND_BACKGROUND_AFTER_COMMIT=true` so jobs only run after committed data is visible.

## What Stays Synchronous

ModelMind still stores the visitor message and assistant message during the request because the response needs their IDs. Provider calls, action extraction, citation extraction, and visible answer cleanup also stay in the chat path.

Everything that can safely happen later is dispatched through Laravel jobs.
