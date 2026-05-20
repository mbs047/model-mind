# Contributing

Thank you for improving ModelMind. This package is designed for Laravel applications, so contributions should keep the package secure, configurable, and easy to install.

## Development Setup

```bash
git clone https://github.com/mbs047/model-mind.git
cd model-mind
composer install
```

## Before Opening a Pull Request

Run the package checks:

```bash
composer validate --strict
composer test
composer format
```

If you changed PHP code, run Pint before committing:

```bash
vendor/bin/pint --format agent
```

## Pull Request Guidelines

- Keep changes focused.
- Add or update tests for behavior changes.
- Update documentation for user-facing changes.
- Do not include unrelated formatting churn.
- Do not commit secrets, API keys, `.env` files, database dumps, or generated vendor files.
- Preserve secure defaults. ModelMind should not expose application data unless the host app explicitly enables it.

## Testing

The package test suite uses Orchestra Testbench and SQLite in memory.

```bash
vendor/bin/phpunit
```

Useful focused command:

```bash
vendor/bin/phpunit --filter ModelMindPackageTest
```

## Documentation Changes

Documentation should be clear enough for a Laravel developer installing the package for the first time.

- Keep `README.md` concise and beginner-friendly.
- Put deeper usage details in `docs/USAGE.md`.
- Put modal design guidance in `docs/CUSTOMIZING-THE-MODAL.md`.
- Update `CHANGELOG.md` for release-worthy changes.

## Security

Do not report security vulnerabilities in public issues. Follow [SECURITY.md](SECURITY.md).
