# Security Policy

ModelMind can read application data that a host Laravel app explicitly enables. Security reports are taken seriously.

## Supported Versions

| Version | Supported |
| --- | --- |
| 1.x | Yes |
| < 1.0 | No |

## Reporting a Vulnerability

Please do not open a public issue for vulnerabilities.

Use GitHub private vulnerability reporting when available, or contact the repository owner through GitHub. Include:

- A clear description of the issue.
- Steps to reproduce.
- Affected version or commit.
- Expected and actual behavior.
- Any proof of concept that does not expose real secrets or private data.

## Sensitive Data Rules

Never include real secrets in reports:

- API keys
- OpenAI keys
- Session tokens
- Passwords
- Private keys
- Customer data
- Database dumps
- Production `.env` files

## Package Security Defaults

ModelMind is designed to be explicit by default:

- Models must be enabled in config.
- Common sensitive columns are blocked.
- Hidden model attributes are respected.
- Encrypted and hashed casts are blocked.
- HTML is stripped from context by default.
- Database content is treated as data, not instructions.

Always inspect enabled context before production use:

```bash
php artisan model-mind:inspect-context
```
