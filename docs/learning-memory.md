# Learning Memory

ModelMind can learn from assistant answers, liked answers, manually fed text, and configured snippets.

```env
MODEL_MIND_LEARNING_ENABLED=true
MODEL_MIND_LEARN_FROM_ASSISTANT_ANSWERS=true
MODEL_MIND_LEARN_FROM_LIKED_ANSWERS=true
MODEL_MIND_LEARN_FROM_FED_TEXTS=true
MODEL_MIND_LEARNING_MIN_CHARACTERS=40
MODEL_MIND_LEARNING_TEXT_CHARACTERS=1200
MODEL_MIND_LEARNING_CONTEXT_LIMIT=12
```

## Feed Text Manually

```bash
php artisan model-mind:learn "Warranty coverage lasts one year." --title="Warranty policy"
```

## Configure Reusable Fed Text

```php
'learning' => [
    'fed_texts' => [
        [
            'title' => 'Support policy',
            'content' => 'Support replies happen within one business day.',
            'source' => 'manual',
        ],
    ],
],
```

Learning has its own sensitive-pattern filter so API keys, tokens, secrets, and passwords are not stored as memories.
