# Presets

ModelMind ships with real configuration presets for common Laravel application types. Presets are recommendations: they do not automatically expose models, because every application has different class names, route names, policies, and sensitive fields.

Available presets:

- `store`: products, orders, stock, pricing, and commerce actions.
- `admin`: users, audit logs, settings, and internal operations.
- `support`: tickets, customers, knowledge base articles, and response guidance.
- `docs`: documentation pages, FAQs, release notes, and troubleshooting.
- `crm`: contacts, companies, deals, ownership, and follow-up workflows.

## List Presets

```bash
php artisan model-mind:preset --list
```

Preview one preset:

```bash
php artisan model-mind:preset store
```

Export the complete JSON recommendation:

```bash
php artisan model-mind:preset store --json
```

## Configure an Active Preset

Use `MODEL_MIND_PRESET` as a project note for the selected setup:

```env
MODEL_MIND_PRESET=store
```

The active preset is shown by `php artisan model-mind:preset --list`. It does not change your live `models` config automatically.

## Copyable Config Payload

The `--json` output includes a `config` section:

```json
{
    "config": {
        "assistant": {
            "default_questions": [
                "Which products are low in stock?",
                "Compare best-selling products by category"
            ]
        },
        "models": {
            "App\\Models\\Product": {
                "enabled": true,
                "label": "Products",
                "columns": "auto"
            }
        },
        "retrieval": {
            "enabled": true,
            "limit": 8
        },
        "security": {
            "max_rows_per_model": 25
        },
        "route_actions": [
            "products.view"
        ]
    }
}
```

Copy the sections you want into `config/model-mind.php`, then adjust:

- Model class names.
- Included and excluded columns.
- Route names and route parameter mappings.
- Authorization, tenant, and Gate settings.
- Default questions and tone for your users.

## Store Preset

Best for storefronts, ecommerce dashboards, and catalog tools.

Recommended model areas:

- `Product`: catalog, stock, price, SKU, category, ratings, summary.
- `Order`: order number, status, total, placed date, customer reference.

Recommended route actions:

- `products.view`
- `orders.view`

## Admin Preset

Best for internal admin panels and operations dashboards.

Recommended model areas:

- `User`: safe user directory fields, role, status, last login.
- `AuditLog`: recent safe audit events and actor context.

Recommended route actions:

- `admin.users.view`

Use Gate or policy checks for admin records.

## Support Preset

Best for support desks and customer service tools.

Recommended model areas:

- `Ticket`: status, priority, subject, customer reference, updated time.
- `KnowledgeBaseArticle`: approved answers, troubleshooting guides, macros.

Recommended route actions:

- `tickets.view`
- `knowledge.view`

## Docs Preset

Best for documentation portals and developer knowledge bases.

Recommended model areas:

- `DocumentationArticle`: title, slug, section, summary, body.
- `ReleaseNote`: version, title, summary, published date.

Recommended route actions:

- `docs.view`

The docs preset uses a larger field character limit because documentation answers often need longer source excerpts.

## CRM Preset

Best for sales, account management, and relationship workflows.

Recommended model areas:

- `Contact`: name, email, status, owner, last contacted date.
- `Deal`: stage, amount, company, owner, close date.

Recommended route actions:

- `contacts.view`
- `deals.view`

The CRM preset recommends user scoping and Gate checks because contacts and deals are usually owner-specific.

## Related Guides

- [Models and Context](models.md)
- [Default Questions](default-questions.md)
- [Question-Aware Retrieval](retrieval.md)
- [Security Controls](security.md)
- [Named Route Actions](route-actions.md)
- [Authorization and User-Aware Context](authorization.md)
