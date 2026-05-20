# Default Questions

Default questions are the starter prompts shown above the input before the visitor asks their first question.

## Environment Configuration

Use a pipe-separated value when you want to manage the questions from `.env`:

```env
MODEL_MIND_DEFAULT_QUESTIONS="Which products are low in stock?|Show recent orders|What policies can you answer from?"
```

## Config File

For more control, set an array in `config/model-mind.php`:

```php
'assistant' => [
    'default_questions' => [
        'Which products are low in stock?',
        'Show recent pending orders',
        'What support policy should I follow?',
    ],
],
```

ModelMind also supports the older `quick_questions` key as a backward-compatible alias. When both are set, `default_questions` wins.

## Per-Render Override

Override questions for a specific layout:

```blade
@modelMind([
    'data' => [
        'quickQuestions' => [
            'Summarize today',
            'Open low-stock products',
        ],
    ],
])
```

For only the modal:

```blade
@modelMindModal([
    'quickQuestions' => [
        'What can you see?',
        'Show featured products',
    ],
])
```

The package sanitizes question text before sending it to the browser config.
