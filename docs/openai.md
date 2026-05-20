# OpenAI Configuration

Use the standard OpenAI environment variables:

```env
OPENAI_API_KEY=sk-your-key
OPENAI_ORGANIZATION=org-your-organization-id
MODEL_MIND_MODEL=gpt-5-nano
```

If ModelMind should use different credentials from the rest of the application, use package-specific values:

```env
MODEL_MIND_OPENAI_API_KEY=sk-your-package-key
MODEL_MIND_OPENAI_ORGANIZATION=org-your-package-organization-id
```

Useful provider settings:

```env
MODEL_MIND_PROVIDER=openai
MODEL_MIND_TIMEOUT=12
MODEL_MIND_CONNECT_TIMEOUT=3
MODEL_MIND_MAX_OUTPUT_TOKENS=450
MODEL_MIND_REASONING_EFFORT=minimal
MODEL_MIND_RETRY_WHEN_TRUNCATED=false
MODEL_MIND_STORE_RESPONSES=false
```

## Model Choice

`MODEL_MIND_MODEL` controls the OpenAI model used by the package. Keep the default small and fast for app-data Q&A, then increase model capability only when answers need deeper reasoning.

## Separate Credentials

Use `MODEL_MIND_OPENAI_API_KEY` and `MODEL_MIND_OPENAI_ORGANIZATION` when the host app already uses OpenAI for other features and ModelMind needs isolated billing, limits, or organization routing.
