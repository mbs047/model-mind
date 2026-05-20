<?php

namespace Mbs\ModelMind\Support\Context;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Mbs\ModelMind\Concerns\HasModelMindContext;
use Mbs\ModelMind\Support\Actions\RouteActionRegistry;
use Mbs\ModelMind\Support\Auth\ModelMindAuthorization;

class ModelContextBuilder
{
    public function __construct(
        private readonly ModelContextDiscoverer $discoverer,
        private readonly RouteActionRegistry $routeActions,
        private readonly ModelMindAuthorization $authorization,
    ) {}

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function build(string $modelClass, array $settings = []): array
    {
        if (($settings['enabled'] ?? true) === false || ! is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        /** @var Model $model */
        $model = new $modelClass;
        $columns = $this->discoverer->columns($model, $settings);

        if ($columns === []) {
            return [];
        }

        try {
            /** @var Builder<Model> $query */
            $query = $modelClass::query()->select($columns);

            foreach ((array) ($settings['relations'] ?? $this->traitRelations($model)) as $relation) {
                if (is_string($relation) && filled($relation)) {
                    $query->with($relation);
                }
            }

            foreach ((array) ($settings['order_by'] ?? []) as $column => $direction) {
                if (is_string($column) && in_array(strtolower((string) $direction), ['asc', 'desc'], true)) {
                    $query->orderBy($column, $direction);
                }
            }

            if (in_array(HasModelMindContext::class, class_uses_recursive($model), true)) {
                $query = $model->modelMindContextQuery($query);
            }

            $query = $this->authorization->scopeQuery($query, $model, $settings);

            $rows = $query
                ->limit(min(
                    (int) ($settings['limit'] ?? config('model-mind.security.max_rows_per_model', 50)),
                    (int) config('model-mind.security.max_rows_per_model', 50),
                ))
                ->get()
                ->map(fn (Model $record): array => $this->recordContext($record, $columns, $settings))
                ->filter()
                ->values()
                ->all();
        } catch (QueryException) {
            $rows = [];
        }

        return [
            'label' => $this->modelLabel($model, $settings),
            'description' => $settings['description'] ?? $this->traitDescription($model),
            'model' => $modelClass,
            'columns' => $columns,
            'route_actions' => $this->routeActionSummaries($model, $settings),
            'rows' => $rows,
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function buildForQuestion(string $modelClass, array $settings, string $question): array
    {
        if (($settings['enabled'] ?? true) === false || ! is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        /** @var Model $model */
        $model = new $modelClass;
        $columns = $this->discoverer->columns($model, $settings);
        $terms = $this->searchTerms($question);
        $searchColumns = $this->searchColumns($columns, $settings);

        if ($columns === [] || $terms === [] || $searchColumns === []) {
            return [];
        }

        try {
            /** @var Builder<Model> $query */
            $query = $this->newContextQuery($modelClass, $model, $columns, $settings);

            foreach ($terms as $term) {
                $query->where(function (Builder $termQuery) use ($searchColumns, $term): void {
                    foreach ($searchColumns as $column) {
                        $termQuery->orWhere($column, 'like', "%{$term}%");
                    }
                });
            }

            $rows = $query
                ->limit(max(1, (int) config('model-mind.retrieval.limit', 8)))
                ->get()
                ->map(fn (Model $record): array => $this->recordContext($record, $columns, $settings))
                ->filter()
                ->values()
                ->all();
        } catch (QueryException) {
            $rows = [];
        }

        if ($rows === []) {
            return [];
        }

        return [
            'label' => $this->modelLabel($model, $settings),
            'description' => $settings['description'] ?? $this->traitDescription($model),
            'model' => $modelClass,
            'matched_terms' => $terms,
            'rows' => $rows,
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $settings
     * @return Builder<Model>
     */
    private function newContextQuery(string $modelClass, Model $model, array $columns, array $settings): Builder
    {
        /** @var Builder<Model> $query */
        $query = $modelClass::query()->select($columns);

        foreach ((array) ($settings['relations'] ?? $this->traitRelations($model)) as $relation) {
            if (is_string($relation) && filled($relation)) {
                $query->with($relation);
            }
        }

        foreach ((array) ($settings['order_by'] ?? []) as $column => $direction) {
            if (is_string($column) && in_array(strtolower((string) $direction), ['asc', 'desc'], true)) {
                $query->orderBy($column, $direction);
            }
        }

        if (in_array(HasModelMindContext::class, class_uses_recursive($model), true)) {
            $query = $model->modelMindContextQuery($query);
        }

        return $this->authorization->scopeQuery($query, $model, $settings);
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function recordContext(Model $record, array $columns, array $settings): array
    {
        if (! $this->authorization->allowsRecord($record, $settings)) {
            return [];
        }

        if (in_array(HasModelMindContext::class, class_uses_recursive($record), true)) {
            $custom = $record->toModelMindContext();

            if ($custom !== []) {
                return $this->withRecordMetadata($record, $settings, $this->cleanArray($custom));
            }
        }

        $context = collect($columns)
            ->mapWithKeys(fn (string $column): array => [$column => $this->cleanValue($record->getAttribute($column))])
            ->reject(fn (mixed $value): bool => $value === null || $value === '' || $value === [])
            ->all();

        return $this->withRecordMetadata($record, $settings, $context);
    }

    /**
     * @return array<int, string>
     */
    private function searchTerms(string $question): array
    {
        $stopWords = collect((array) config('model-mind.retrieval.stop_words', []))
            ->filter(fn (mixed $word): bool => is_string($word))
            ->map(fn (string $word): string => str($word)->lower()->toString())
            ->all();
        $minLength = max(1, (int) config('model-mind.retrieval.min_term_length', 2));
        $maxTerms = max(1, (int) config('model-mind.retrieval.max_terms', 8));

        return str($question)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->explode(' ')
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => $term !== ''
                && mb_strlen($term) >= $minLength
                && ! in_array($term, $stopWords, true))
            ->unique()
            ->take($maxTerms)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    private function searchColumns(array $columns, array $settings): array
    {
        $configuredColumns = array_values(array_filter((array) ($settings['search_columns'] ?? []), 'is_string'));
        $preferredColumns = $configuredColumns !== []
            ? $configuredColumns
            : [
                'name',
                'title',
                'sku',
                'slug',
                'brand',
                'category',
                'label',
                'summary',
                'short_description',
                'description',
                'body',
                'image_label',
                'color_name',
            ];

        return collect($preferredColumns)
            ->filter(fn (string $column): bool => in_array($column, $columns, true))
            ->filter(fn (string $column): bool => preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) === 1)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function traitRelations(Model $model): array
    {
        if (! in_array(HasModelMindContext::class, class_uses_recursive($model), true)) {
            return [];
        }

        return $model->modelMindContextRelations();
    }

    private function traitLabel(Model $model): ?string
    {
        return in_array(HasModelMindContext::class, class_uses_recursive($model), true)
            ? $model->modelMindLabel()
            : null;
    }

    private function traitDescription(Model $model): ?string
    {
        return in_array(HasModelMindContext::class, class_uses_recursive($model), true)
            ? $model->modelMindDescription()
            : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function modelLabel(Model $model, array $settings): string
    {
        $label = $settings['label'] ?? $this->traitLabel($model) ?? class_basename($model);

        return is_scalar($label)
            ? str((string) $label)->squish()->limit(100, '')->toString()
            : class_basename($model);
    }

    private function cleanValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->cleanArray($value);
        }

        if (! is_scalar($value)) {
            return null;
        }

        $text = (string) $value;

        if (config('model-mind.security.strip_html', true)) {
            $text = strip_tags($text);
        }

        return str($text)
            ->squish()
            ->limit((int) config('model-mind.security.field_character_limit', 900), '')
            ->toString();
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function cleanArray(array $values): array
    {
        return collect($values)
            ->map(fn (mixed $value): mixed => $this->cleanValue($value))
            ->reject(fn (mixed $value): bool => $value === null || $value === '' || $value === [])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array{key: string, label: string}>
     */
    private function routeActionSummaries(Model $model, array $settings): array
    {
        return collect($this->routeActions->actionsForModel($model::class, $settings))
            ->map(fn (array $action): array => [
                'key' => $action['key'],
                'label' => $action['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function withRecordMetadata(Model $record, array $settings, array $context): array
    {
        $actions = $this->routeActions->actionsForRecord($record, $settings);

        if ($this->sourceCitationsEnabled()) {
            $context = [
                ...$context,
                'model_mind_source' => $this->sourceCitation($record, $settings, $context),
            ];
        }

        if ($actions !== []) {
            $context = [
                ...$context,
                'model_mind_route_actions' => $actions,
            ];
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $context
     * @return array{key: string, model: string, record: string, columns: array<int, string>, token: string}
     */
    private function sourceCitation(Model $record, array $settings, array $context): array
    {
        $columns = $this->sourceColumns($context);
        $key = $this->sourceKey($record, $context);

        return [
            'key' => $key,
            'model' => $this->modelLabel($record, $settings),
            'record' => $this->sourceRecordLabel($record, $settings, $context),
            'columns' => $columns,
            'token' => sprintf('[[%s key="%s"]]', $this->sourceToken(), addcslashes($key, '\\"')),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function sourceColumns(array $context): array
    {
        return collect($context)
            ->keys()
            ->filter(fn (mixed $column): bool => is_string($column)
                && ! str_starts_with($column, 'model_mind_')
                && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) === 1)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function sourceKey(Model $record, array $context): string
    {
        $identifier = $record->getKey();
        $identifier = is_scalar($identifier)
            ? (string) $identifier
            : json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return substr(hash('sha256', $record::class.'|'.($identifier ?: spl_object_id($record))), 0, 16);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $context
     */
    private function sourceRecordLabel(Model $record, array $settings, array $context): string
    {
        $template = $this->cleanSourceLabelTemplate($settings['source_label_template'] ?? '');

        if ($template !== '') {
            $rendered = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', function (array $match) use ($record, $context): string {
                $column = (string) ($match[1] ?? '');
                $value = $context[$column] ?? $record->getAttribute($column);

                return is_scalar($value) ? $this->cleanSourceLabel($value) : '';
            }, $template) ?? $template;

            $rendered = $this->cleanSourceLabel($rendered);

            if ($rendered !== '') {
                return $rendered;
            }
        }

        $configuredColumn = $this->cleanColumnName($settings['source_label_column'] ?? '');
        $labelColumns = collect([
            $configuredColumn,
            ...((array) config('model-mind.citations.label_columns', [])),
        ])
            ->filter(fn (mixed $column): bool => is_string($column) && $this->cleanColumnName($column) !== '')
            ->unique()
            ->values()
            ->all();

        foreach ($labelColumns as $column) {
            $value = $context[$column] ?? $record->getAttribute($column);

            if (is_scalar($value)) {
                $label = $this->cleanSourceLabel($value);

                if ($label !== '') {
                    return $label;
                }
            }
        }

        $key = $record->getKey();

        return is_scalar($key) && filled((string) $key)
            ? '#'.str((string) $key)->squish()->limit(40, '')->toString()
            : class_basename($record);
    }

    private function sourceCitationsEnabled(): bool
    {
        return (bool) config('model-mind.features.citations', true)
            && (bool) config('model-mind.citations.enabled', true);
    }

    private function sourceToken(): string
    {
        $token = config('model-mind.citations.token', 'model_mind_source');

        return is_string($token) && preg_match('/^[A-Za-z][A-Za-z0-9_:-]*$/', $token) === 1
            ? $token
            : 'model_mind_source';
    }

    private function cleanColumnName(mixed $name): string
    {
        return is_string($name) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1 ? $name : '';
    }

    private function cleanSourceLabel(mixed $label): string
    {
        if (! is_scalar($label)) {
            return '';
        }

        return str(strip_tags((string) $label))->squish()->limit(100, '')->toString();
    }

    private function cleanSourceLabelTemplate(mixed $template): string
    {
        if (! is_scalar($template)) {
            return '';
        }

        return str(strip_tags((string) $template))->squish()->limit(140, '')->toString();
    }
}
