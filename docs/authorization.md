# Authorization and User-Aware Context

ModelMind can scope context to the current authenticated user, guard, tenant, and Laravel Gate or policy checks. Use this for admin panels, SaaS apps, customer portals, and any application where two visitors should not see the same records.

## What It Adds

- Current authenticated user context.
- Configurable auth guard.
- Optional user roles and permissions.
- Tenant-aware query scoping.
- User-owned record scoping.
- Laravel Gate and policy checks.
- Per-model authorization callbacks.
- Trait-level authorization defaults.
- Safe route action resolution for authorized records only.

## Global Settings

```php
'authorization' => [
    'enabled' => true,
    'guard' => env('MODEL_MIND_AUTH_GUARD'),
    'include_user_context' => true,
    'user_columns' => [
        'id',
        'name',
        'email',
    ],
    'roles' => [
        'enabled' => true,
        'methods' => [
            'getRoleNames',
            'roles',
        ],
        'limit' => 20,
    ],
    'permissions' => [
        'enabled' => false,
        'methods' => [
            'getAllPermissions',
            'permissions',
        ],
        'limit' => 30,
    ],
],
```

`user_columns` still passes through ModelMind's sensitive-column filter, so fields such as `password`, tokens, secrets, encrypted casts, and blocked patterns are not added to the prompt.

## Tenant Scoping

ModelMind can resolve a tenant from a fixed config value, a callback, a request attribute, or the current user.

```php
'authorization' => [
    'tenant' => [
        'enabled' => true,
        'id' => env('MODEL_MIND_TENANT_ID'),
        'resolver' => null,
        'request_attribute' => 'tenant_id',
        'user_column' => 'tenant_id',
        'model_column' => 'tenant_id',
    ],
],
```

For common SaaS apps, adding `tenant_id` to the authenticated user and tenant-owned models is enough. ModelMind will scope enabled models by that tenant when `scope_to_tenant` is enabled for the model.

## Per-Model Authorization

Add an `authorization` block to each enabled model:

```php
App\Models\Order::class => [
    'enabled' => true,
    'label' => 'Orders',
    'columns' => 'auto',
    'authorization' => [
        'scope_to_user' => true,
        'user_column' => 'user_id',
        'scope_to_tenant' => true,
        'tenant_column' => 'tenant_id',
        'gate' => true,
        'ability' => 'view',
    ],
],
```

This applies before the model enters the assistant context and before route action buttons are resolved.

## Gate and Policies

When `gate` is enabled, ModelMind calls Laravel Gate with the configured ability:

```php
'authorization' => [
    'gate' => true,
    'ability' => 'view',
],
```

If your app has an `OrderPolicy::view(User $user, Order $order)` policy, records that fail the policy are not included in context, and route action tokens for those records will not become buttons.

If no Gate ability or policy exists for the model, ModelMind allows the record. That keeps existing public catalog use cases working while making policy-backed apps safer.

## Authorization Callbacks

For complex rules, use callbacks:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;

'authorization' => [
    'query' => function (Builder $query, ?Authenticatable $user): Builder {
        return $query->where('status', 'published');
    },
    'record' => function (?Authenticatable $user, Model $record): bool {
        return $user !== null && $record->team_id === $user->team_id;
    },
],
```

You can also point to invokable classes or `Class@method` strings if you prefer keeping authorization logic outside the config file.

## Trait Defaults

Models that use `HasModelMindContext` can define authorization beside the model:

```php
use Mbs\ModelMind\Concerns\HasModelMindContext;

class Order extends Model
{
    use HasModelMindContext;

    public function modelMindAuthorization(): array
    {
        return [
            'scope_to_user' => true,
            'user_column' => 'customer_id',
            'scope_to_tenant' => true,
            'tenant_column' => 'tenant_id',
            'gate' => true,
            'ability' => 'view',
        ];
    }
}
```

Config values override trait defaults when both are provided.

## Route Actions Stay Authorized

ModelMind also checks authorization when turning route tokens into buttons. If an answer includes a token for a record the current user cannot view, the token is discarded and no button is returned.

## Caching Note

When authorization depends on the current user, tenant, Gate, or callbacks, ModelMind bypasses the global context cache for that request. This avoids leaking one user's scoped context into another user's chat.

## Production Checklist

- Use `scope_to_tenant` for tenant-owned models.
- Use `scope_to_user` for customer-owned models.
- Keep `gate` enabled for policy-backed records.
- Include only safe `user_columns`.
- Run `php artisan model-mind:inspect-context` while authenticated as the kind of user you want to test.
- Test route actions for records the user can and cannot access.
