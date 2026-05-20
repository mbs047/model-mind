<?php

namespace Mbs\ModelMind\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Mbs\ModelMind\Concerns\HasModelMindContext;
use Mbs\ModelMind\Support\Context\SensitiveColumnFilter;
use Throwable;

class ModelMindAuthorization
{
    public function __construct(private readonly SensitiveColumnFilter $filter) {}

    public function enabled(): bool
    {
        return (bool) config('model-mind.authorization.enabled', true);
    }

    public function requiresUncachedContext(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if ($this->user() instanceof Authenticatable && (bool) config('model-mind.authorization.include_user_context', true)) {
            return true;
        }

        if ($this->tenantId() !== null) {
            return true;
        }

        foreach ((array) config('model-mind.models', []) as $modelClass => $settings) {
            if (is_int($modelClass) && is_string($settings)) {
                $modelClass = $settings;
                $settings = [];
            }

            if (! is_array($settings)) {
                continue;
            }

            $model = is_string($modelClass) && is_subclass_of($modelClass, Model::class)
                ? new $modelClass
                : null;
            $authorization = $this->modelSettings($settings, $model);

            if (
                $authorization['enabled']
                && (
                    ($authorization['gate'] && $this->gateConfigured($authorization['ability'], $model))
                    || $authorization['scope_to_user']
                    || ($authorization['scope_to_tenant'] && $this->tenantId() !== null)
                    || $authorization['query'] !== null
                    || $authorization['record'] !== null
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $user = $this->user();
        $tenantId = $this->tenantId();
        $context = [
            'enabled' => true,
            'guard' => $this->guardName(),
            'authenticated' => $user instanceof Authenticatable,
        ];

        if ($user instanceof Authenticatable && (bool) config('model-mind.authorization.include_user_context', true)) {
            $context['current_user'] = $this->userContext($user);
        }

        if ($tenantId !== null) {
            $context['tenant'] = [
                'id' => $tenantId,
            ];
        }

        return $context;
    }

    public function user(): ?Authenticatable
    {
        $guard = $this->configuredGuard();

        if ($guard !== null) {
            try {
                $user = Auth::guard($guard)->user();

                if ($user instanceof Authenticatable) {
                    return $user;
                }
            } catch (Throwable) {
                return null;
            }
        }

        try {
            $user = request()->user();

            if ($user instanceof Authenticatable) {
                return $user;
            }
        } catch (Throwable) {
            //
        }

        try {
            $user = Auth::user();

            return $user instanceof Authenticatable ? $user : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $settings
     * @return Builder<Model>
     */
    public function scopeQuery(Builder $query, Model $model, array $settings): Builder
    {
        $authorization = $this->modelSettings($settings, $model);

        if (! $authorization['enabled']) {
            return $query;
        }

        $user = $this->user();
        $tenantId = $this->tenantId();

        if ($authorization['scope_to_user']) {
            if (! $user instanceof Authenticatable) {
                return $query->whereRaw('1 = 0');
            }

            $userColumn = $authorization['user_column'];

            if ($this->hasColumn($model, $userColumn)) {
                $query->where($userColumn, $user->getAuthIdentifier());
            }
        }

        if ($authorization['scope_to_tenant'] && $tenantId !== null) {
            $tenantColumn = $authorization['tenant_column'];

            if ($this->hasColumn($model, $tenantColumn)) {
                $query->where($tenantColumn, $tenantId);
            }
        }

        $scoped = $this->callConfigured($authorization['query'], [$query, $user, $model, $settings]);

        return $scoped instanceof Builder ? $scoped : $query;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function allowsRecord(Model $record, array $settings): bool
    {
        $authorization = $this->modelSettings($settings, $record);

        if (! $authorization['enabled']) {
            return true;
        }

        $user = $this->user();

        if ($authorization['scope_to_user']) {
            if (! $user instanceof Authenticatable) {
                return false;
            }

            $userColumn = $authorization['user_column'];

            if ($this->hasColumn($record, $userColumn) && (string) $record->getAttribute($userColumn) !== (string) $user->getAuthIdentifier()) {
                return false;
            }
        }

        if ($authorization['scope_to_tenant']) {
            $tenantId = $this->tenantId();
            $tenantColumn = $authorization['tenant_column'];

            if ($tenantId !== null && $this->hasColumn($record, $tenantColumn) && (string) $record->getAttribute($tenantColumn) !== (string) $tenantId) {
                return false;
            }
        }

        $recordDecision = $this->callConfigured($authorization['record'], [$user, $record, $settings]);

        if (is_bool($recordDecision)) {
            return $recordDecision;
        }

        if (! $authorization['gate']) {
            return true;
        }

        return $this->gateAllows($authorization['ability'], $record, $user);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{
     *     enabled: bool,
     *     gate: bool,
     *     ability: string,
     *     scope_to_user: bool,
     *     user_column: string,
     *     scope_to_tenant: bool,
     *     tenant_column: string,
     *     query: mixed,
     *     record: mixed
     * }
     */
    private function modelSettings(array $settings, ?Model $model = null): array
    {
        $modelDefaults = (array) config('model-mind.authorization.models', []);
        $configured = $settings['authorization'] ?? [];

        if ($configured === false || ! $this->enabled()) {
            return [
                'enabled' => false,
                'gate' => false,
                'ability' => 'view',
                'scope_to_user' => false,
                'user_column' => 'user_id',
                'scope_to_tenant' => false,
                'tenant_column' => 'tenant_id',
                'query' => null,
                'record' => null,
            ];
        }

        if ($configured === true || ! is_array($configured)) {
            $configured = [];
        }

        if ($model instanceof Model && in_array(HasModelMindContext::class, class_uses_recursive($model), true)) {
            $configured = [
                ...$model->modelMindAuthorization(),
                ...$configured,
            ];
        }

        return [
            'enabled' => (bool) ($configured['enabled'] ?? true),
            'gate' => (bool) ($configured['gate'] ?? $configured['use_gate'] ?? $modelDefaults['use_gate'] ?? true),
            'ability' => $this->cleanAbility($configured['ability'] ?? $configured['gate_ability'] ?? $modelDefaults['ability'] ?? 'view'),
            'scope_to_user' => (bool) ($configured['scope_to_user'] ?? $modelDefaults['scope_to_user'] ?? false),
            'user_column' => $this->cleanColumn($configured['user_column'] ?? $modelDefaults['user_column'] ?? 'user_id'),
            'scope_to_tenant' => (bool) ($configured['scope_to_tenant'] ?? $modelDefaults['scope_to_tenant'] ?? true),
            'tenant_column' => $this->cleanColumn($configured['tenant_column'] ?? $configured['tenant_id_column'] ?? $modelDefaults['tenant_column'] ?? config('model-mind.authorization.tenant.model_column', 'tenant_id')),
            'query' => $configured['query'] ?? $configured['scope'] ?? null,
            'record' => $configured['record'] ?? $configured['can'] ?? $configured['can_view'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userContext(Authenticatable $user): array
    {
        $context = [
            'model' => $user::class,
            'id' => $user->getAuthIdentifier(),
        ];

        foreach ($this->userColumns($user) as $column) {
            $value = $this->userValue($user, $column);

            if ($value !== null && $value !== '') {
                $context[$column] = $value;
            }
        }

        $roles = $this->userList($user, 'roles');

        if ($roles !== []) {
            $context['roles'] = $roles;
        }

        $permissions = $this->userList($user, 'permissions');

        if ($permissions !== []) {
            $context['permissions'] = $permissions;
        }

        return $context;
    }

    /**
     * @return array<int, string>
     */
    private function userColumns(Authenticatable $user): array
    {
        $casts = $user instanceof Model ? $user->getCasts() : [];

        return collect((array) config('model-mind.authorization.user_columns', []))
            ->filter(fn (mixed $column): bool => is_string($column) && $this->filter->allowed($column, $casts[$column] ?? null))
            ->map(fn (string $column): string => $this->cleanColumn($column))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function userValue(Authenticatable $user, string $column): mixed
    {
        if ($column === 'id') {
            return $user->getAuthIdentifier();
        }

        if ($user instanceof Model) {
            return $this->cleanValue($user->getAttribute($column));
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function userList(Authenticatable $user, string $key): array
    {
        $config = (array) config("model-mind.authorization.{$key}", []);

        if (! (bool) ($config['enabled'] ?? false)) {
            return [];
        }

        $values = null;

        foreach ((array) ($config['methods'] ?? []) as $method) {
            if (! is_string($method) || blank($method)) {
                continue;
            }

            if ($user instanceof Model && in_array($method, ['roles', 'permissions'], true)) {
                $values = $user->getAttribute($method);
            } elseif (method_exists($user, $method)) {
                $values = $user->{$method}();
            }

            if ($values !== null) {
                break;
            }
        }

        return $this->normalizeList($values, max(0, (int) ($config['limit'] ?? 20)));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeList(mixed $values, int $limit): array
    {
        if ($limit === 0 || $values === null) {
            return [];
        }

        if ($values instanceof Collection) {
            $values = $values->all();
        }

        if (! is_iterable($values)) {
            $values = [$values];
        }

        return collect($values)
            ->map(fn (mixed $value): ?string => $this->stringFromValue($value))
            ->filter()
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    private function stringFromValue(mixed $value): ?string
    {
        if (is_scalar($value)) {
            return $this->cleanValue($value);
        }

        if ($value instanceof Model) {
            foreach (['name', 'title', 'label', 'slug', 'id'] as $column) {
                $attribute = $value->getAttribute($column);

                if (is_scalar($attribute) && filled((string) $attribute)) {
                    return $this->cleanValue($attribute);
                }
            }
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return $this->cleanValue((string) $value);
        }

        return null;
    }

    private function tenantId(): string|int|null
    {
        if (! (bool) config('model-mind.authorization.tenant.enabled', true)) {
            return null;
        }

        $configured = config('model-mind.authorization.tenant.id');

        if (is_scalar($configured) && filled((string) $configured)) {
            return $configured;
        }

        $resolved = $this->callConfigured(config('model-mind.authorization.tenant.resolver'), [$this->user()]);
        $resolved = $this->tenantValue($resolved);

        if ($resolved !== null) {
            return $resolved;
        }

        $requestAttribute = config('model-mind.authorization.tenant.request_attribute', 'tenant_id');

        if (is_string($requestAttribute) && filled($requestAttribute)) {
            try {
                $resolved = request()->attributes->get($requestAttribute);
                $resolved = $this->tenantValue($resolved);

                if ($resolved !== null) {
                    return $resolved;
                }
            } catch (Throwable) {
                //
            }
        }

        $user = $this->user();
        $userColumn = $this->cleanColumn(config('model-mind.authorization.tenant.user_column', 'tenant_id'));

        if ($user instanceof Model && $userColumn !== '') {
            return $this->tenantValue($user->getAttribute($userColumn));
        }

        return null;
    }

    private function tenantValue(mixed $value): string|int|null
    {
        if (is_scalar($value) && filled((string) $value)) {
            return is_int($value) ? $value : (string) $value;
        }

        if ($value instanceof Model) {
            $key = $value->getKey();

            return is_scalar($key) && filled((string) $key) ? $key : null;
        }

        return null;
    }

    private function gateAllows(string $ability, Model $record, ?Authenticatable $user): bool
    {
        if (! $this->gateConfigured($ability, $record)) {
            return true;
        }

        try {
            return Gate::forUser($user)->allows($ability, $record);
        } catch (Throwable) {
            return false;
        }
    }

    private function gateConfigured(string $ability, ?Model $record): bool
    {
        if (Gate::has($ability)) {
            return true;
        }

        return $record instanceof Model && Gate::getPolicyFor($record) !== null;
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    private function callConfigured(mixed $callback, array $parameters): mixed
    {
        try {
            if (is_string($callback) && str_contains($callback, '@')) {
                [$class, $method] = explode('@', $callback, 2);

                return app($class)->{$method}(...$parameters);
            }

            if (is_string($callback) && class_exists($callback) && method_exists($callback, '__invoke')) {
                return app($callback)(...$parameters);
            }

            if (is_callable($callback)) {
                return $callback(...$parameters);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function hasColumn(Model $model, string $column): bool
    {
        if ($column === '') {
            return false;
        }

        try {
            return Schema::hasColumn($model->getTable(), $column);
        } catch (Throwable) {
            return false;
        }
    }

    private function configuredGuard(): ?string
    {
        $guard = config('model-mind.authorization.guard');

        return is_string($guard) && filled($guard) ? $guard : null;
    }

    private function guardName(): string
    {
        return $this->configuredGuard()
            ?? (is_string(config('auth.defaults.guard')) ? config('auth.defaults.guard') : 'web');
    }

    private function cleanColumn(mixed $column): string
    {
        return is_string($column) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) === 1 ? $column : '';
    }

    private function cleanAbility(mixed $ability): string
    {
        return is_string($ability) && preg_match('/^[A-Za-z0-9_.:-]{1,80}$/', $ability) === 1 ? $ability : 'view';
    }

    private function cleanValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $value = (string) $value;

        if ((bool) config('model-mind.security.strip_html', true)) {
            $value = strip_tags($value);
        }

        return str($value)
            ->squish()
            ->limit((int) config('model-mind.security.field_character_limit', 600), '')
            ->toString();
    }
}
