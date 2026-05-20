# Package Development

For contributors working inside the package repository:

```bash
composer install
composer validate --strict
composer test
composer format
```

The package test suite uses Orchestra Testbench and SQLite in memory.

## Before Opening a Pull Request

- Run `composer validate --strict`.
- Run `vendor/bin/pint --format agent`.
- Run `vendor/bin/phpunit`.
- Keep behavior secure by default.
- Update the relevant feature doc when changing package behavior.
