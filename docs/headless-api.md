# Headless API

ModelMind can run without the Blade modal. Use the headless JSON API for React, Vue, Inertia, Livewire-heavy custom screens, native mobile apps, or any client that wants to build its own chat UI.

## Enable API Mode

The API route group is enabled by default.

```env
MODEL_MIND_API_ENABLED=true
MODEL_MIND_API_PREFIX=api/model-mind
MODEL_MIND_API_ROUTE_NAME=model-mind.api.
MODEL_MIND_API_RATE_LIMIT=30
```

```php
'api' => [
    'enabled' => true,
    'prefix' => 'api/model-mind',
    'name' => 'model-mind.api.',
    'middleware' => ['api', 'throttle:model-mind-api'],
    'rate_limit' => 30,
],
```

For authenticated SPAs or mobile apps, add your normal API guard middleware:

```php
'middleware' => ['api', 'auth:sanctum', 'throttle:model-mind-api'],
```

## Endpoints

Default endpoints:

- `GET /api/model-mind/manifest`
- `POST /api/model-mind/chat`
- `GET /api/model-mind/session?session_id={session_id}`
- `POST /api/model-mind/messages/{message}/feedback`

The web Blade modal still uses the existing `/model-mind/*` web routes. API mode is separate so custom clients can be stateless and carry `session_id` themselves.

## Manifest

Use the manifest to bootstrap a custom UI without hardcoding labels or endpoints.

```http
GET /api/model-mind/manifest
Accept: application/json
```

Response:

```json
{
    "name": "ModelMind",
    "brand_mark": "MBS",
    "placeholder": "Ask about the enabled application data",
    "default_questions": ["What can you help with?"],
    "features": {
        "feedback": true,
        "actions": true,
        "citations": true
    },
    "endpoints": {
        "chat": "https://example.test/api/model-mind/chat",
        "session": "https://example.test/api/model-mind/session",
        "feedback": "https://example.test/api/model-mind/messages/{message}/feedback"
    },
    "limits": {
        "question_characters": 2000,
        "history_messages": 20,
        "history_message_characters": 10000,
        "feedback_note_characters": 1000
    },
    "session_lifetime_minutes": 120
}
```

## Send a Message

```http
POST /api/model-mind/chat
Accept: application/json
Content-Type: application/json
```

```json
{
    "session_id": null,
    "question": "Which products are low in stock?",
    "history": [
        {"role": "user", "content": "Show inventory"},
        {"role": "assistant", "content": "I can answer from enabled inventory data."}
    ]
}
```

Response:

```json
{
    "answer": "Samsung Galaxy S24 Ultra is low in stock.",
    "actions": [
        {
            "label": "View Samsung Galaxy S24 Ultra",
            "url": "https://example.test/products/1",
            "kind": "route"
        }
    ],
    "citations": [
        {
            "model": "Products",
            "record": "Samsung Galaxy S24 Ultra",
            "source": "Products: Samsung Galaxy S24 Ultra",
            "columns": ["name", "stock"],
            "action": {
                "label": "View Samsung Galaxy S24 Ultra",
                "url": "https://example.test/products/1",
                "kind": "route"
            }
        }
    ],
    "session_id": "9c575b06-8c3f-4b62-82e1-3c50859e6fd8",
    "expires_at": "2026-05-21T18:00:00.000000Z",
    "user_message_id": "f5c8c7f6-4f12-4b35-9b65-77d3a7f7564e",
    "message_id": "d9d54b44-14d7-4779-b91d-96abcc0dd5ec"
}
```

Store the returned `session_id` in your client and send it with future questions.

## Restore a Session

```http
GET /api/model-mind/session?session_id=9c575b06-8c3f-4b62-82e1-3c50859e6fd8
Accept: application/json
```

Response:

```json
{
    "session_id": "9c575b06-8c3f-4b62-82e1-3c50859e6fd8",
    "expires_at": "2026-05-21T18:00:00.000000Z",
    "expired": false,
    "messages": [
        {
            "id": "f5c8c7f6-4f12-4b35-9b65-77d3a7f7564e",
            "role": "user",
            "content": "Which products are low in stock?",
            "actions": [],
            "citations": [],
            "feedback": null,
            "created_at": "2026-05-21T16:00:00.000000Z"
        }
    ]
}
```

If the session expired, the response returns `expired: true`, `session_id: null`, and an empty message list. Start a new chat request without `session_id`.

## Send Feedback

Replace `{message}` with the assistant `message_id`.

```http
POST /api/model-mind/messages/d9d54b44-14d7-4779-b91d-96abcc0dd5ec/feedback
Accept: application/json
Content-Type: application/json
```

```json
{
    "session_id": "9c575b06-8c3f-4b62-82e1-3c50859e6fd8",
    "feedback": "liked",
    "note": "Correct product and useful link."
}
```

## JavaScript Example

```js
const manifest = await fetch('/api/model-mind/manifest', {
    headers: { Accept: 'application/json' },
}).then((response) => response.json());

const response = await fetch(manifest.endpoints.chat, {
    method: 'POST',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        session_id: localStorage.getItem('modelmind_session_id'),
        question: 'Show low stock products',
    }),
}).then((response) => response.json());

localStorage.setItem('modelmind_session_id', response.session_id);
```

## Client Responsibilities

- Send `Accept: application/json`.
- Store and resend `session_id`.
- Render `answer` as text.
- Render `actions` as trusted buttons or links.
- Render `citations` as source cards if you want auditability.
- Send feedback only for assistant `message_id` values.
- Use your normal API authentication and CORS policy for external clients.

## Related Guides

- [Package Routes](package-routes.md)
- [Named Route Actions](route-actions.md)
- [Source Citations](source-citations.md)
- [Sessions](sessions.md)
- [Authorization and User-Aware Context](authorization.md)
