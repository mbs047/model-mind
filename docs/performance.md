# Performance

Good defaults for most apps:

```env
MODEL_MIND_CONTEXT_CACHE_SECONDS=600
MODEL_MIND_RETRIEVAL_ENABLED=true
MODEL_MIND_RETRIEVAL_LIMIT=8
MODEL_MIND_RECENT_MESSAGES=8
MODEL_MIND_BROWSER_MESSAGES=60
MODEL_MIND_MESSAGE_CHARACTERS=800
MODEL_MIND_SUMMARY_CHARACTERS=2000
MODEL_MIND_MAX_OUTPUT_TOKENS=450
MODEL_MIND_TIMEOUT=12
MODEL_MIND_CONNECT_TIMEOUT=3
MODEL_MIND_STREAMING=true
```

## Large Applications

- Keep per-model `limit` small.
- Keep question-aware retrieval enabled for large searchable models.
- Enable streaming so visitors see partial answer text while the provider is still generating.
- Prefer scoped public records with `modelMindContextQuery()`.
- Use custom context providers for summarized analytics instead of sending many raw rows.
- Run `php artisan model-mind:inspect-context` and check the resulting character size.
- Keep `MODEL_MIND_MAX_OUTPUT_TOKENS` low for faster chat responses.

Clear cached context after imports or config changes:

```bash
php artisan model-mind:clear-context
```
