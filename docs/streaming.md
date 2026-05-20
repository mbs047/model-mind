# Streaming Responses

Streaming makes the assistant feel faster by showing answer text as the provider produces it. The normal JSON chat endpoint remains available, so you can enable streaming only when your provider and UI are ready.

## Enable Streaming

```env
MODEL_MIND_STREAMING=true
```

```php
'features' => [
    'streaming' => true,
],
```

When enabled, the default Blade modal posts to `/model-mind/stream` and reads Server-Sent Events. When disabled, it posts to `/model-mind/chat` and waits for the full JSON response.

## Web Widget Events

The stream endpoint emits these events:

```text
event: ready
data: {"session_id":"...","expires_at":"...","user_message_id":"..."}

event: delta
data: {"delta":"Partial answer text"}

event: done
data: {"answer":"Final cleaned answer","actions":[],"citations":[],"session_id":"...","expires_at":"...","user_message_id":"...","message_id":"..."}
```

If the provider fails after the stream starts, ModelMind emits:

```text
event: error
data: {"message":"ModelMind is unavailable right now. Please try again soon.","session_id":"..."}
```

The `done` event is the authoritative response. ModelMind still extracts safe route actions, source citations, feedback IDs, and learning memory after the provider finishes.

## Headless Clients

Headless clients can use `POST /api/model-mind/stream`.

```js
const response = await fetch('/api/model-mind/stream', {
    method: 'POST',
    headers: {
        Accept: 'application/json, text/event-stream',
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        session_id: localStorage.getItem('modelmind_session_id'),
        question: 'Which products are low in stock?',
    }),
});

const reader = response.body.getReader();
const decoder = new TextDecoder();
let buffer = '';

while (true) {
    const { done, value } = await reader.read();

    if (done) {
        break;
    }

    buffer += decoder.decode(value, { stream: true });
    // Parse event/data blocks separated by a blank line.
}
```

Use `delta` for the live typing UI, then replace the draft answer with the final `done.answer` and render `done.actions` plus `done.citations`.

## Custom Providers

Existing providers that only implement `ModelMindProvider` keep working. If streaming is enabled and a provider does not implement streaming, ModelMind sends the full answer as one `delta` event.

To stream tokens from a custom provider, also implement `StreamingModelMindProvider`:

```php
use Mbs\ModelMind\Contracts\ModelMindProvider;
use Mbs\ModelMind\Contracts\StreamingModelMindProvider;
use Mbs\ModelMind\Data\ModelMindRequestData;
use Mbs\ModelMind\Data\ModelMindResponseData;

class CustomProvider implements ModelMindProvider, StreamingModelMindProvider
{
    public function answer(ModelMindRequestData $request): ModelMindResponseData
    {
        return new ModelMindResponseData('Full answer.');
    }

    public function stream(ModelMindRequestData $request): iterable
    {
        yield 'Partial ';
        yield 'answer.';
    }

    public function streamMetadata(ModelMindRequestData $request): array
    {
        return ['model' => 'custom-streaming-model'];
    }
}
```

## Production Notes

- Keep `MODEL_MIND_TIMEOUT` high enough for your longest streamed answer.
- If you use Nginx, the package sends `X-Accel-Buffering: no`; confirm your hosting layer does not buffer event streams.
- Use the JSON `/chat` endpoint for clients that cannot read `ReadableStream`.
- Route actions and citations are finalized at the end of the stream so unsafe or invalid tokens are still removed server-side.
