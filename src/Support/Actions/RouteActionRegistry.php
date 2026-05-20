<?php

namespace Mbs\ModelMind\Support\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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
- If you answer in a non-English language, still copy route action tokens exactly as written. Do not translate the token name, key, parameter names, quotes, or values.
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
            'label' => $this->label($definition, $routeParameters, $label),
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
                $parameters = $this->routeParametersForRecord($definition, $record);

                return $parameters === null ? null : [
                    'key' => $definition['key'],
                    'label' => $this->labelForRecord($definition, $record),
                    'token' => $this->tokenFor($definition['key'], $parameters),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, url: string, kind: string}>
     */
    public function inferredActionsForAnswer(string $answer): array
    {
        if (! (bool) config('model-mind.actions.infer_from_answer', true)) {
            return [];
        }

        $normalizedAnswer = $this->normalizeForMatching($answer);

        if ($normalizedAnswer === '') {
            return [];
        }

        $actions = [];
        $maxActions = max(0, (int) config('model-mind.actions.max_actions', 5));

        if ($maxActions === 0) {
            return [];
        }

        foreach ((array) config('model-mind.models', []) as $modelClass => $settings) {
            if (! is_string($modelClass) || ! is_array($settings) || ($settings['enabled'] ?? true) === false) {
                continue;
            }

            $definitions = $this->modelDefinitions($modelClass, $settings);

            if ($definitions === []) {
                continue;
            }

            foreach ($this->candidateRecordsForAnswer($modelClass, $settings, $definitions, $answer) as $record) {
                if (! $this->answerMentionsRecord($normalizedAnswer, $record, $settings, $definitions)) {
                    continue;
                }

                foreach ($definitions as $definition) {
                    $parameters = $this->routeParametersForRecord($definition, $record);

                    if ($parameters === null) {
                        continue;
                    }

                    $action = $this->resolve($definition['key'], $parameters);

                    if ($action !== null) {
                        $key = strtolower(rtrim($action['url'], '/'));
                        $actions[$key] ??= $action;
                    }

                    if (count($actions) >= $maxActions) {
                        break 3;
                    }
                }
            }
        }

        return array_values($actions);
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
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $settings
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<int, Model>
     */
    private function candidateRecordsForAnswer(string $modelClass, array $settings, array $definitions, string $answer): array
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        try {
            /** @var Model $model */
            $model = new $modelClass;
            $columns = $this->matchColumns($model, $settings, $definitions);

            /** @var Builder<Model> $query */
            $query = $modelClass::query();

            if (in_array(HasModelMindContext::class, class_uses_recursive($model), true)) {
                $query = $model->modelMindContextQuery($query);
            }

            $terms = $this->matchTerms($answer);

            if ($terms !== [] && $columns !== []) {
                $query->where(function ($termQuery) use ($columns, $terms): void {
                    foreach ($terms as $term) {
                        foreach ($columns as $column) {
                            $termQuery->orWhere($column, 'like', "%{$term}%");
                        }
                    }
                });
            }

            foreach ((array) ($settings['order_by'] ?? []) as $column => $direction) {
                if (is_string($column) && in_array(strtolower((string) $direction), ['asc', 'desc'], true)) {
                    $query->orderBy($column, $direction);
                }
            }

            return $query
                ->limit(max(1, (int) config('model-mind.actions.inference_limit', 50)))
                ->get()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<int, string>
     */
    private function matchColumns(Model $model, array $settings, array $definitions): array
    {
        try {
            $tableColumns = Schema::getColumnListing($model->getTable());
        } catch (Throwable) {
            $tableColumns = [];
        }

        if ($tableColumns === []) {
            return [];
        }

        $columns = [
            ...(array) ($settings['search_columns'] ?? []),
            'name',
            'title',
            'sku',
            'slug',
            'code',
            'brand',
            'category',
            'label',
            'order_number',
            'reference',
        ];

        foreach ($definitions as $definition) {
            $columns[] = $definition['label_column'] ?? null;

            foreach ((array) ($definition['parameters'] ?? []) as $sourceColumn) {
                $columns[] = $sourceColumn;
            }

            preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', (string) ($definition['label_template'] ?? ''), $matches);
            $columns = [...$columns, ...($matches[1] ?? [])];
        }

        return collect($columns)
            ->filter(fn (mixed $column): bool => is_string($column) && in_array($column, $tableColumns, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function matchTerms(string $answer): array
    {
        return str($answer)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->explode(' ')
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->unique()
            ->take(30)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, array<string, mixed>>  $definitions
     */
    private function answerMentionsRecord(string $normalizedAnswer, Model $record, array $settings, array $definitions): bool
    {
        foreach ($this->recordMatchPhrases($record, $settings, $definitions) as $phrase) {
            $normalizedPhrase = $this->normalizeForMatching($phrase);

            if (
                mb_strlen($normalizedPhrase) >= 4
                && ! preg_match('/^\d+(?:\s+\d+)*$/', $normalizedPhrase)
                && str_contains(" {$normalizedAnswer} ", " {$normalizedPhrase} ")
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<int, string>
     */
    private function recordMatchPhrases(Model $record, array $settings, array $definitions): array
    {
        $phrases = [];
        $columns = $this->matchColumns($record, $settings, $definitions);

        foreach ($definitions as $definition) {
            $phrases[] = $this->labelForRecord($definition, $record);
        }

        foreach ($columns as $column) {
            $value = $record->getAttribute($column);

            if (is_scalar($value)) {
                $phrases[] = $this->cleanLabel($value);
            }
        }

        return collect($phrases)
            ->filter(fn (mixed $phrase): bool => is_string($phrase) && filled($phrase))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, label: string, label_column: string, label_template: string, description: string, route: string, parameters: array<string, string>, kind: string, model: class-string<Model>|null}|null
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
            'label_column' => $this->cleanColumnName($settings['label_column'] ?? $settings['label_from'] ?? ''),
            'label_template' => $this->cleanLabelTemplate($settings['label_template'] ?? ''),
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
     * @param  array<string, mixed>  $definition
     * @return array<string, string>|null
     */
    private function routeParametersForRecord(array $definition, Model $record): ?array
    {
        $parameters = [];

        foreach ((array) ($definition['parameters'] ?? []) as $name => $source) {
            if (! is_string($name) || ! is_string($source)) {
                return null;
            }

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

        return $parameters;
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

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, string>  $routeParameters
     */
    private function label(array $definition, array $routeParameters, ?string $label): string
    {
        $recordLabel = $this->labelForRouteParameters($definition, $routeParameters);

        if ($recordLabel !== null) {
            return $recordLabel;
        }

        if ((bool) config('model-mind.actions.allow_label_override', false) && is_string($label) && filled($label)) {
            return $this->cleanLabel($label);
        }

        return $definition['label'];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, string>  $routeParameters
     */
    private function labelForRouteParameters(array $definition, array $routeParameters): ?string
    {
        if (
            ! is_string($definition['model'] ?? null)
            || (blank($definition['label_column'] ?? '') && blank($definition['label_template'] ?? ''))
            || ! is_subclass_of($definition['model'], Model::class)
        ) {
            return null;
        }

        foreach ((array) ($definition['parameters'] ?? []) as $routeName => $sourceColumn) {
            if (! is_string($routeName) || ! is_string($sourceColumn) || ! array_key_exists($routeName, $routeParameters)) {
                continue;
            }

            try {
                /** @var class-string<Model> $modelClass */
                $modelClass = $definition['model'];
                $record = $modelClass::query()
                    ->where($sourceColumn, $routeParameters[$routeName])
                    ->first();
            } catch (Throwable) {
                return null;
            }

            return $record instanceof Model
                ? $this->labelForRecord($definition, $record)
                : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function labelForRecord(array $definition, Model $record): string
    {
        $column = is_string($definition['label_column'] ?? null) ? $definition['label_column'] : '';
        $value = $column !== '' ? $record->getAttribute($column) : null;
        $value = is_scalar($value) ? $this->cleanLabel($value) : '';
        $template = is_string($definition['label_template'] ?? null) ? $definition['label_template'] : '';

        if ($template !== '') {
            $rendered = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', function (array $match) use ($definition, $record, $value): string {
                $placeholder = (string) ($match[1] ?? '');

                if ($placeholder === 'label') {
                    return (string) $definition['label'];
                }

                if ($placeholder === 'value') {
                    return $value;
                }

                $attribute = $record->getAttribute($placeholder);

                return is_scalar($attribute) ? $this->cleanLabel($attribute) : '';
            }, $template) ?? $template;

            return $this->cleanLabel($rendered);
        }

        return $value !== '' ? $value : (string) $definition['label'];
    }

    private function cleanKey(string $key): string
    {
        return str($key)->squish()->replaceMatches('/[^A-Za-z0-9_.:-]/', '.')->trim('.')->limit(100, '')->toString();
    }

    private function cleanParameterName(string $name): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1 ? $name : '';
    }

    private function cleanColumnName(mixed $name): string
    {
        return is_string($name) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1 ? $name : '';
    }

    private function cleanLabelTemplate(mixed $template): string
    {
        if (! is_scalar($template)) {
            return '';
        }

        return str(strip_tags((string) $template))
            ->squish()
            ->limit(120, '')
            ->toString();
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

    private function normalizeForMatching(string $value): string
    {
        return str($value)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->squish()
            ->toString();
    }
}
