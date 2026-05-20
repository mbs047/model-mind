# Performance

Good defaults for most apps:

```env
MODEL_MIND_CONTEXT_CACHE_SECONDS=600
MODEL_MIND_RECENT_MESSAGES=8
MODEL_MIND_BROWSER_MESSAGES=60
MODEL_MIND_MESSAGE_CHARACTERS=800
MODEL_MIND_SUMMARY_CHARACTERS=2000
MODEL_MIND_MAX_OUTPUT_TOKENS=450
MODEL_MIND_TIMEOUT=12
MODEL_MIND_CONNECT_TIMEOUT=3
```

## Large Applications

- Keep per-model `limit` small.
- Prefer scoped public records with `modelMindContextQuery()`.
- Use custom context providers for summarized analytics instead of sending many raw rows.
- Run `php artisan model-mind:inspect-context` and check the resulting character size.
- Keep `MODEL_MIND_MAX_OUTPUT_TOKENS` low for faster chat responses.
