# Sessions

ModelMind stores conversations in package tables and mirrors recent messages in browser storage so visitors can keep context across page loads.

## Session Lifetime

Configure the inactivity window in minutes:

```env
MODEL_MIND_SESSION_LIFETIME_MINUTES=120
```

```php
'memory' => [
    'session_lifetime_minutes' => 120,
],
```

After that period, ModelMind starts a fresh session instead of continuing the old conversation. This reset applies to both:

- The server session restored from `model_mind_sessions`.
- The browser state stored under `model-mind-state`.

Set the value to `0` to disable automatic expiry:

```env
MODEL_MIND_SESSION_LIFETIME_MINUTES=0
```

## Related Memory Settings

```php
'memory' => [
    'recent_messages' => 8,
    'browser_messages' => 60,
    'message_characters' => 800,
    'summary_characters' => 2000,
    'context_cache_seconds' => 600,
    'session_lifetime_minutes' => 120,
],
```

`recent_messages` controls how much recent conversation enters the prompt. `browser_messages` controls how many recent messages the widget can restore in the browser. `session_lifetime_minutes` controls when the conversation should be treated as expired.
