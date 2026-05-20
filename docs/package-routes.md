# Package Routes

Default route prefix:

```env
MODEL_MIND_ROUTE_PREFIX=model-mind
MODEL_MIND_ROUTE_NAME=model-mind.
```

The package registers:

- `POST /model-mind/chat`
- `GET /model-mind/session`
- `POST /model-mind/messages/{message}/feedback`

The default middleware is:

```php
['web', 'throttle:model-mind']
```

Inspect the registered routes in a host application:

```bash
php artisan route:list --name=model-mind
```

For assistant-generated application links, read [Named Route Actions](route-actions.md).
