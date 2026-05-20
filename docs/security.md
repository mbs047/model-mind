# Security Controls

ModelMind is explicit and deny-first.

## Defaults

- It does not expose all application models automatically.
- It filters common sensitive columns such as passwords, tokens, secrets, private keys, recovery codes, bank fields, and identity fields.
- It respects model `$hidden` and `$visible`.
- It blocks encrypted and hashed casts by default.
- It strips HTML from model context values by default.
- It treats database values as data, not instructions.

## Important Settings

```php
'security' => [
    'auto_discover_models' => false,
    'strip_html' => true,
    'field_character_limit' => 600,
    'max_rows_per_model' => 25,
    'max_context_characters' => 12000,
    'blocked_columns' => [
        'password',
        'remember_token',
        'api_token',
    ],
    'blocked_patterns' => [
        '/password/i',
        '/token/i',
        '/secret/i',
    ],
],
```

## Production Checklist

- Enable only the models the assistant should know about.
- Prefer explicit `include` and `exclude` lists for sensitive models.
- Use `modelMindContextQuery()` to restrict records.
- Run `php artisan model-mind:inspect-context`.
- Review [SECURITY.md](../SECURITY.md) before reporting vulnerabilities or enabling sensitive production data.
