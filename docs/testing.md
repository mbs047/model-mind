# Testing

After installation in an app, test these paths:

```bash
php artisan model-mind:inspect-context
php artisan model-mind:clear-context
php artisan route:list --name=model-mind
php artisan test
```

## Browser Checks

- Open the chat.
- Ask a question from an enabled model.
- Refresh and confirm history restores.
- Mark an answer as `Helpful`.
- Check light and dark appearances.
- Confirm hidden or sensitive fields never appear.
- Confirm named route actions open the expected pages.
- Ask for a record outside the static context window and confirm question-aware retrieval finds it.

## Custom Design Checks

After changing the modal:

```bash
php artisan route:list --name=model-mind
php artisan model-mind:inspect-context
php artisan test
```

Manual browser checks:

- Open and close the widget.
- Submit a question.
- Confirm the submit button disables while empty.
- Confirm history restores after refresh.
- Select `Helpful` or `Not helpful`.
- Check `MODEL_MIND_THEME=light`, `MODEL_MIND_THEME=dark`, and `MODEL_MIND_THEME=auto`.
- Check all configured positions.
