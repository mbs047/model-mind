<?php

namespace Mbs\ModelMind\Support\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Mbs\ModelMind\Concerns\HasModelMindContext;
use Throwable;

class RouteActionRegistry
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        $definitions = [];

        foreach ((array) config('model-mind.actions.routes', []) as $key => $settings) {
            $definition = $this->normalizeDefinition($key, $settings);

            if ($definition !== null) {
                $definitions[$definition['key']] = $definition;
            }
        }

        foreach ((array) config('model-mind.models', []) as $modelClass => $settings) {
            if (! is_string($modelClass) || ! is_array($settings) || ($settings['enabled'] ?? true) === false) {
                continue;
            }

            foreach ($this->modelDefinitions($modelClass, $settings) as $definition) {
                $definitions[$definition['key']] = $definition;
            }
        }

        return array_values($definitions);
    }

    public function promptInstructions(): string
    {
        $definitions = $this->definitions();

        if ($definitions === []) {
            return '';
        }

        $token = $this->token();
        $lines = collect($definitions)
            ->map(function (array $definition) use ($token): string {
                $parameters = collect($definition['parameters'])
                    ->map(fn (string $source, string $name): string => $source === $name ? $name : "{$name} from {$source}")
                    ->implode(', ');
                $parameters = $parameters !== '' ? $parameters : 'none';
                $description = filled($definition['description']) ? " {$definition['description']}" : '';

                return sprintf(
                    '- %s: "%s".%s Parameters: %s. Syntax: [[%s key="%s"%s]]',
                    $definition['key'],
                    $definition['label'],
                    $description,
                    $parameters,
                    $token,
                    $definition['key'],
                    $this->syntaxParameters($definition['parameters']),
                );
            })
            ->implode("\n");

        return <<<PROMPT

Route actions:
- When a clickable application link is useful, append one route action token on its own line.
- Use only the route actions listed here. Do not invent route names, URLs, keys, or parameters.
- Use route action tokens only when you have the required parameter values in enabled context.
- The application will convert valid route action tokens into buttons and remove the token from the visitor-facing answer.
{$lines}
PROMPT;
    }

    /**
     * @param  array<string, string>  $parameters
     * @return array{label: string, url: string, kind: string}|null
     */
    public function resolve(string $key, array $parameters = [], ?string $label = null): ?array
    {
        $definition = $this->definition($key);

        if ($definition === null || ! Route::has($definition['route'])) {
            return null;
        }

        $routeParameters = [];

        foreach ($definition['parameters'] as $name => $source) {
            $value = $parameters[$name] ?? $parameters[$source] ?? null;

            if (! is_scalar($value)) {
                return null;
            }

            $value = $this->cleanParameterValue((string) $value);

            if ($value === '') {
                return null;
            }

            $routeParameters[$name] = $value;
        }

        try {
            $url = route($definition['route'], $routeParameters);
        } catch (Throwable) {
            return null;
        }

        return [
            'label' => $this->label($definition, $label),
            'url' => $url,
            'kind' => $definition['kind'],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array{key: string, label: string, parameters: array<string, string>}>
     */
    public function actionsForModel(string $modelClass, array $settings = []): array
    {
        return collect($this->modelDefinitions($modelClass, $settings))
            ->map(fn (array $definition): array => [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'parameters' => $definition['parameters'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array{key: string, label: string, token: string}>
     */
    public function actionsForRecord(Model $record, array $settings = []): array
    {
        return collect($this->modelDefinitions($record::class, $settings))
            ->map(function (array $definition) use ($record): ?array {
                $parameters = [];

                foreach ($definition['parameters'] as $name => $source) {
                    $value = $record->getAttribute($source);

                    if (! is_scalar($value)) {
                        return null;
                    }

                    $value = $this->cleanParameterValue((string) $value);

                    if ($value === '') {
                        return null;
                    }

                    $parameters[$name] = $value;
                }

                return [
                    'key' => $definition['key'],
                    'label' => $definition['label'],
                    'token' => $this->tokenFor($definition['key'], $parameters),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function modelDefinitions(string $modelClass, array $settings = []): array
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        /** @var Model $model */
        $model = new $modelClass;
        $configured = array_merge(
            (array) ($settings['actions'] ?? []),
            (array) ($settings['route_actions'] ?? []),
        );

        if (in_array(HasModelMindContext::class, class_uses_recursive($model), true)) {
            $configured = array_merge($configured, $model->modelMindRouteActions());
        }

        return collect($configured)
            ->map(fn (mixed $definition, string|int $key): ?array => $this->normalizeDefinition($key, $definition, $modelClass))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function definition(string $key): ?array
    {
        return collect($this->definitions())->first(fn (array $definition): bool => $definition['key'] === $key);
    }

    /**
     * @return array{key: string, label: string, description: string, route: string, parameters: array<string, string>, kind: string, model: class-string<Model>|null}|null
     */
    private function normalizeDefinition(string|int $key, mixed $settings, ?string $modelClass = null): ?array
    {
        if (! is_array($settings)) {
            return null;
        }

        $key = is_string($key) && filled($key) ? $key : (string) ($settings['key'] ?? '');
        $route = $settings['route'] ?? null;

        if (! is_string($key) || ! is_string($route) || blank($key) || blank($route)) {
            return null;
        }

        $key = $this->cleanKey($key);

        if ($key === '') {
            return null;
        }

        return [
            'key' => $key,
            'label' => $this->cleanLabel($settings['label'] ?? str($key)->headline()->toString()),
            'description' => $this->cleanDescription($settings['description'] ?? ''),
            'route' => $route,
            'parameters' => $this->normalizeParameters($settings['parameters'] ?? []),
            'kind' => $this->cleanKind($settings['kind'] ?? 'route'),
            'model' => is_string($modelClass) && is_subclass_of($modelClass, Model::class) ? $modelClass : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function normalizeParameters(mixed $parameters): array
    {
        return collect((array) $parameters)
            ->mapWithKeys(function (mixed $source, string|int $name): array {
                if (is_int($name)) {
                    $name = is_string($source) ? $source : '';
                }

                if (! is_string($name) || blank($name)) {
                    return [];
                }

                return [$this->cleanParameterName($name) => $this->cleanParameterName(is_string($source) && filled($source) ? $source : $name)];
            })
            ->reject(fn (string $source, string $name): bool => $name === '' || $source === '')
            ->all();
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function tokenFor(string $key, array $parameters): string
    {
        $attributes = ['key' => $key, ...$parameters];

        return sprintf('[[%s %s]]', $this->token(), collect($attributes)
            ->map(fn (string $value, string $name): string => sprintf('%s="%s"', $name, addcslashes($value, '\\"')))
            ->implode(' '));
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function syntaxParameters(array $parameters): string
    {
        return collect($parameters)
            ->keys()
            ->map(fn (string $name): string => sprintf(' %s="<value>"', $name))
            ->implode('');
    }

    public function token(): string
    {
        $token = config('model-mind.actions.route_token', 'model_mind_route');

        return is_string($token) && preg_match('/^[A-Za-z][A-Za-z0-9_:-]*$/', $token) === 1
            ? $token
            : 'model_mind_route';
    }

    private function label(array $definition, ?string $label): string
    {
        if ((bool) config('model-mind.actions.allow_label_override', false) && is_string($label) && filled($label)) {
            return $this->cleanLabel($label);
        }

        return $definition['label'];
    }

    private function cleanKey(string $key): string
    {
        return str($key)->squish()->replaceMatches('/[^A-Za-z0-9_.:-]/', '.')->trim('.')->limit(100, '')->toString();
    }

    private function cleanParameterName(string $name): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1 ? $name : '';
    }

    private function cleanParameterValue(string $value): string
    {
        return str($value)
            ->squish()
            ->replaceMatches('/["\'\[\]]/', '')
            ->limit(200, '')
            ->toString();
    }

    private function cleanLabel(mixed $label): string
    {
        $label = is_scalar($label) ? (string) $label : 'Open';

        return str(strip_tags($label))->squish()->limit(80, '')->toString() ?: 'Open';
    }

    private function cleanDescription(mixed $description): string
    {
        $description = is_scalar($description) ? (string) $description : '';

        return str(strip_tags($description))->squish()->limit(180, '')->toString();
    }

    private function cleanKind(mixed $kind): string
    {
        $kind = is_scalar($kind) ? (string) $kind : 'route';

        return preg_match('/^[A-Za-z0-9_-]{1,40}$/', $kind) === 1 ? $kind : 'route';
    }
}
