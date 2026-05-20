# Multilingual Answers

ModelMind can speak with visitors in different languages while reading from the same enabled application data.

For example, your products, orders, and policies can be stored in English, while a visitor asks in Arabic. ModelMind can answer in Arabic from that English database context without requiring duplicate translated tables.

## Configuration

The default behavior is to answer in the same language as the latest visitor message:

```env
MODEL_MIND_LANGUAGE="Answer in the same language as the latest visitor message unless explicitly asked otherwise."
```

```php
'assistant' => [
    'language_instructions' => env(
        'MODEL_MIND_LANGUAGE',
        'Answer in the same language as the latest visitor message unless explicitly asked otherwise.',
    ),
],
```

You can make this stricter for a single-language support experience:

```env
MODEL_MIND_LANGUAGE="Always answer in Arabic. Product names, SKUs, route tokens, and technical identifiers must stay exactly as written."
```

## Route Buttons

Named route actions are still safe in multilingual chats. The AI is instructed to keep route tokens exactly as written, even when the visible answer is translated.

If the AI answers in another language and mentions a configured record without copying the route token, ModelMind can infer the approved route action from clearly mentioned enabled record labels:

```env
MODEL_MIND_INFER_ROUTE_ACTIONS=true
```

This only creates buttons from routes you configured in `route_actions` or `actions.routes`; it does not let the AI invent URLs.

## Best Practices

- Keep product names, SKUs, order numbers, and route parameters in the enabled context.
- Add `search_columns` for the fields visitors are likely to mention.
- Use `label_column` or `label_template` so buttons show clear record names.
- Keep `MODEL_MIND_INFER_ROUTE_ACTIONS=true` for multilingual storefronts and support tools.
- Use `MODEL_MIND_LANGUAGE` to match your support policy.

## Related Guides

- [Models and Context](models.md)
- [Named Route Actions](route-actions.md)
- [Examples](examples.md)
