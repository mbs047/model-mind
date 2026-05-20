# Package Routes

Default route prefix:

```env
MODEL_MIND_ROUTE_PREFIX=model-mind
MODEL_MIND_ROUTE_NAME=model-mind.
MODEL_MIND_API_ENABLED=true
MODEL_MIND_API_PREFIX=api/model-mind
MODEL_MIND_API_ROUTE_NAME=model-mind.api.
```

The package registers:

- `POST /model-mind/chat`
- `GET /model-mind/session`
- `POST /model-mind/messages/{message}/feedback`
- `GET /api/model-mind/manifest`
- `POST /api/model-mind/chat`
- `GET /api/model-mind/session`
- `POST /api/model-mind/messages/{message}/feedback`

The default web middleware is:

```php
['web', 'throttle:model-mind']
```

The default API middleware is:

```php
['api', 'throttle:model-mind-api']
```

Inspect the registered routes in a host application:

```bash
php artisan route:list --name=model-mind
```

Disable the headless API route group when you only want the Blade widget:

```env
MODEL_MIND_API_ENABLED=false
```

For assistant-generated application links, read [Named Route Actions](route-actions.md).

For React, Vue, Inertia, or mobile clients, read [Headless API](headless-api.md).
