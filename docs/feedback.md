# Feedback

When feedback is enabled, assistant messages can be marked:

- `Helpful`
- `Not helpful`

After a visitor selects one option, the selected button is highlighted and the opposite option is disabled.

Feedback submissions are also recorded as usage analytics events when analytics are enabled.

## Learning From Feedback

Helpful answers can be saved into learning memory when `from_liked_answers` is enabled.

```php
'learning' => [
    'from_liked_answers' => true,
],
```

## Disable Feedback

```php
'features' => [
    'feedback' => false,
],
```
