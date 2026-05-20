<?php

namespace Mbs\ModelMind\Support\Context;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Mbs\ModelMind\Concerns\HasModelMindContext;
use Mbs\ModelMind\Contracts\ModelMindVectorSearcher;
use Mbs\ModelMind\Support\Actions\RouteActionRegistry;
use Mbs\ModelMind\Support\Auth\ModelMindAuthorization;
use Mbs\ModelMind\Support\Retrieval\RetrievalNormalizer;
use Throwable;

class ModelContextBuilder
{
    public function __construct(
        private readonly ModelContextDiscoverer $discoverer,
        private readonly RouteActionRegistry $routeActions,
        private readonly ModelMindAuthorization $authorization,
        private readonly RetrievalNormalizer $retrievalNormalizer,
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
        $terms = $this->retrievalNormalizer->terms($question);
        $searchColumns = $this->searchColumns($columns, $settings);

        if ($columns === [] || $terms === [] || $searchColumns === []) {
            return [];
        }

        $retrieved = $this->retrievedQuestionRecords($modelClass, $model, $columns, $settings, $question, $terms, $searchColumns);
        $rows = collect($retrieved['records'])
            ->map(fn (Model $record): array => $this->recordContext($record, $columns, $settings))
            ->filter()
            ->values()
            ->all();

        if ($rows === []) {
            return [];
        }

        return [
            'label' => $this->modelLabel($model, $settings),
            'description' => $settings['description'] ?? $this->traitDescription($model),
            'model' => $modelClass,
            'matched_terms' => $terms,
            'retrieval' => [
                'engine' => $retrieved['engine'],
                'ranked' => true,
                'columns' => $searchColumns,
                'weights' => $this->columnWeights($searchColumns, $settings),
                'scores' => (bool) config('model-mind.retrieval.include_scores', true) ? $retrieved['scores'] : [],
            ],
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
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $settings
     * @param  array<int, string>  $terms
     * @param  array<int, string>  $searchColumns
     * @return array{engine: string, records: array<int, Model>, scores: array<int, array{record: string, score: float, columns: array<int, string>}>}
     */
    private function retrievedQuestionRecords(string $modelClass, Model $model, array $columns, array $settings, string $question, array $terms, array $searchColumns): array
    {
        $limit = max(1, (int) config('model-mind.retrieval.limit', 8));

        $vectorRecords = $this->vectorRecords($modelClass, $model, $columns, $settings, $question, $limit);

        if ($vectorRecords !== []) {
            return [
                'engine' => 'vector',
                'records' => $vectorRecords,
                'scores' => [],
            ];
        }

        $scoutRecords = $this->scoutRecords($modelClass, $model, $columns, $settings, $question, $limit);

        if ($scoutRecords !== []) {
            return [
                'engine' => 'scout',
                'records' => $scoutRecords,
                'scores' => [],
            ];
        }

        return $this->databaseRankedRecords($modelClass, $model, $columns, $settings, $question, $terms, $searchColumns, $limit);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $settings
     * @return array<int, Model>
     */
    private function vectorRecords(string $modelClass, Model $model, array $columns, array $settings, string $question, int $limit): array
    {
        if (! (bool) config('model-mind.retrieval.vector.enabled', false)) {
            return [];
        }

        $searcherClass = config('model-mind.retrieval.vector.searcher');

        if (! is_string($searcherClass) || ! class_exists($searcherClass)) {
            return [];
        }

        $searcher = app($searcherClass);

        if (! $searcher instanceof ModelMindVectorSearcher) {
            return [];
        }

        try {
            return $this->recordsFromReferences(
                $modelClass,
                $model,
                $columns,
                $settings,
                $searcher->search($question, $modelClass, $settings, $columns, max(1, (int) config('model-mind.retrieval.vector.limit', $limit))),
                $limit,
            );
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $settings
     * @return array<int, Model>
     */
    private function scoutRecords(string $modelClass, Model $model, array $columns, array $settings, string $question, int $limit): array
    {
        if (! (bool) config('model-mind.retrieval.scout.enabled', false) || ! is_callable([$modelClass, 'search'])) {
            return [];
        }

        try {
            $builder = $modelClass::search($question);
            $scoutLimit = max(1, (int) config('model-mind.retrieval.scout.limit', $limit));

            if (is_object($builder) && method_exists($builder, 'take')) {
                $builder = $builder->take($scoutLimit);
            }

            if (! is_object($builder) || ! method_exists($builder, 'get')) {
                return [];
            }

            return $this->recordsFromReferences($modelClass, $model, $columns, $settings, $builder->get(), $limit);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $settings
     * @param  iterable<int, Model|int|string|array<string, mixed>>  $references
     * @return array<int, Model>
     */
    private function recordsFromReferences(string $modelClass, Model $model, array $columns, array $settings, iterable $references, int $limit): array
    {
        $keys = collect($references)
            ->map(function (mixed $reference): mixed {
                if ($reference instanceof Model) {
                    return $reference->getKey();
                }

                if (is_array($reference)) {
                    if (($reference['model'] ?? null) instanceof Model) {
                        return $reference['model']->getKey();
                    }

                    if (($reference['record'] ?? null) instanceof Model) {
                        return $reference['record']->getKey();
                    }

                    return $reference['id'] ?? $reference['key'] ?? $reference['record'] ?? $reference['model'] ?? null;
                }

                return is_scalar($reference) ? $reference : null;
            })
            ->filter(fn (mixed $key): bool => is_scalar($key) && filled((string) $key))
            ->take(max(1, $this->candidateLimit()))
            ->values()
            ->all();

        if ($keys === []) {
            return [];
        }

        try {
            $queryColumns = collect([$model->getKeyName(), ...$columns])
                ->filter(fn (mixed $column): bool => is_string($column) && filled($column))
                ->unique()
                ->values()
                ->all();
            $records = $this->newContextQuery($modelClass, $model, $queryColumns, $settings)
                ->whereKey($keys)
                ->get()
                ->filter(fn (Model $record): bool => $this->authorization->allowsRecord($record, $settings))
                ->keyBy(fn (Model $record): string => (string) $record->getKey());

            return collect($keys)
                ->map(fn (mixed $key): ?Model => $records->get((string) $key))
                ->filter()
                ->take($limit)
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $settings
     * @param  array<int, string>  $terms
     * @param  array<int, string>  $searchColumns
     * @return array{engine: string, records: array<int, Model>, scores: array<int, array{record: string, score: float, columns: array<int, string>}>}
     */
    private function databaseRankedRecords(string $modelClass, Model $model, array $columns, array $settings, string $question, array $terms, array $searchColumns, int $limit): array
    {
        $candidates = collect($this->databaseCandidates($modelClass, $model, $columns, $settings, $terms, $searchColumns, true));

        if ((bool) config('model-mind.retrieval.fuzzy.enabled', true)) {
            $fallback = collect($this->databaseCandidates($modelClass, $model, $columns, $settings, $terms, $searchColumns, false));
            $candidates = $candidates
                ->merge($fallback)
                ->unique(fn (Model $record): string => (string) ($record->getKey() ?? spl_object_id($record)))
                ->values();
        }

        $ranked = $this->rankRecords($candidates->all(), $question, $terms, $searchColumns, $settings)
            ->filter(fn (array $rankedRecord): bool => $rankedRecord['score'] >= max(0.0, (float) config('model-mind.retrieval.min_score', 0.1)))
            ->take($limit)
            ->values();

        return [
            'engine' => 'ranked_database',
            'records' => $ranked->pluck('record')->all(),
            'scores' => $ranked
                ->map(fn (array $rankedRecord): array => [
                    'record' => $this->recordDebugLabel($rankedRecord['record']),
                    'score' => round((float) $rankedRecord['score'], 3),
                    'columns' => array_values($rankedRecord['columns']),
                ])
                ->all(),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $settings
     * @param  array<int, string>  $terms
     * @param  array<int, string>  $searchColumns
     * @return array<int, Model>
     */
    private function databaseCandidates(string $modelClass, Model $model, array $columns, array $settings, array $terms, array $searchColumns, bool $filtered): array
    {
        try {
            $query = $this->newContextQuery($modelClass, $model, $columns, $settings);

            if ($filtered) {
                $query->where(function (Builder $candidateQuery) use ($searchColumns, $terms): void {
                    foreach ($terms as $term) {
                        foreach ($searchColumns as $column) {
                            $candidateQuery->orWhere($column, 'like', "%{$term}%");
                        }
                    }
                });
            }

            return $query
                ->limit($this->candidateLimit())
                ->get()
                ->filter(fn (Model $record): bool => $this->authorization->allowsRecord($record, $settings))
                ->values()
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @param  array<int, Model>  $records
     * @param  array<int, string>  $terms
     * @param  array<int, string>  $searchColumns
     * @param  array<string, mixed>  $settings
     */
    private function rankRecords(array $records, string $question, array $terms, array $searchColumns, array $settings): Collection
    {
        $normalizedQuestion = $this->retrievalNormalizer->normalize($question);
        $weights = $this->columnWeights($searchColumns, $settings);

        return collect($records)
            ->map(function (Model $record, int $index) use ($normalizedQuestion, $terms, $searchColumns, $weights): array {
                $scored = $this->scoreRecord($record, $normalizedQuestion, $terms, $searchColumns, $weights);

                return [
                    'record' => $record,
                    'score' => $scored['score'],
                    'columns' => $scored['columns'],
                    'index' => $index,
                ];
            })
            ->sort(function (array $left, array $right): int {
                $scoreComparison = $right['score'] <=> $left['score'];

                return $scoreComparison !== 0 ? $scoreComparison : ($left['index'] <=> $right['index']);
            });
    }

    /**
     * @param  array<int, string>  $terms
     * @param  array<int, string>  $searchColumns
     * @param  array<string, float>  $weights
     * @return array{score: float, columns: array<int, string>}
     */
    private function scoreRecord(Model $record, string $normalizedQuestion, array $terms, array $searchColumns, array $weights): array
    {
        $score = 0.0;
        $matchedColumns = [];

        foreach ($searchColumns as $column) {
            $value = $record->getAttribute($column);

            if (! is_scalar($value) || blank((string) $value)) {
                continue;
            }

            $normalizedValue = $this->retrievalNormalizer->normalize((string) $value);

            if ($normalizedValue === '') {
                continue;
            }

            $weight = $weights[$column] ?? 1.0;
            $columnScore = 0.0;

            if ($normalizedQuestion !== '' && str_contains(" {$normalizedValue} ", " {$normalizedQuestion} ")) {
                $columnScore += $weight * 4.0;
            }

            foreach ($terms as $term) {
                if (str_contains(" {$normalizedValue} ", " {$term} ")) {
                    $columnScore += $weight * 1.5;

                    continue;
                }

                if (str_contains($normalizedValue, $term)) {
                    $columnScore += $weight;

                    continue;
                }

                $columnScore += $this->fuzzyScore($term, $normalizedValue, $weight);
            }

            if ($columnScore > 0) {
                $matchedColumns[] = $column;
                $score += $columnScore;
            }
        }

        return [
            'score' => $score,
            'columns' => array_values(array_unique($matchedColumns)),
        ];
    }

    private function fuzzyScore(string $term, string $normalizedValue, float $weight): float
    {
        if (! (bool) config('model-mind.retrieval.fuzzy.enabled', true) || mb_strlen($term) < 4) {
            return 0.0;
        }

        $maxDistance = max(0, (int) config('model-mind.retrieval.fuzzy.max_distance', 2));
        $minSimilarity = max(0.0, min(1.0, (float) config('model-mind.retrieval.fuzzy.min_similarity', 0.72)));

        foreach ($this->retrievalNormalizer->tokens($normalizedValue) as $token) {
            if (abs(mb_strlen($token) - mb_strlen($term)) > $maxDistance) {
                continue;
            }

            $distance = levenshtein($term, $token);

            if ($distance > $maxDistance) {
                continue;
            }

            $similarity = 1 - ($distance / max(strlen($term), strlen($token), 1));

            if ($similarity >= $minSimilarity) {
                return $weight * $similarity * 0.75;
            }
        }

        return 0.0;
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    private function searchColumns(array $columns, array $settings): array
    {
        $configuredColumns = collect((array) ($settings['search_columns'] ?? []))
            ->map(fn (mixed $value, string|int $key): mixed => is_string($key) ? $key : $value)
            ->filter(fn (mixed $column): bool => is_string($column))
            ->values()
            ->all();
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
     * @param  array<int, string>  $searchColumns
     * @param  array<string, mixed>  $settings
     * @return array<string, float>
     */
    private function columnWeights(array $searchColumns, array $settings): array
    {
        $configuredSearchColumns = collect((array) ($settings['search_columns'] ?? []))
            ->filter(fn (mixed $value, string|int $key): bool => is_string($key) && is_numeric($value))
            ->mapWithKeys(fn (mixed $value, string $key): array => [$key => (float) $value])
            ->all();
        $modelWeights = collect((array) ($settings['retrieval']['weights'] ?? $settings['retrieval_weights'] ?? []))
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value)
            ->all();
        $globalWeights = collect((array) config('model-mind.retrieval.column_weights', []))
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value)
            ->all();

        return collect($searchColumns)
            ->mapWithKeys(fn (string $column): array => [
                $column => max(0.1, (float) ($configuredSearchColumns[$column] ?? $modelWeights[$column] ?? $globalWeights[$column] ?? 1.0)),
            ])
            ->all();
    }

    private function candidateLimit(): int
    {
        return max(
            max(1, (int) config('model-mind.retrieval.limit', 8)),
            (int) config('model-mind.retrieval.candidate_limit', 60),
        );
    }

    private function recordDebugLabel(Model $record): string
    {
        foreach ((array) config('model-mind.citations.label_columns', []) as $column) {
            if (! is_string($column)) {
                continue;
            }

            $value = $record->getAttribute($column);

            if (is_scalar($value) && filled((string) $value)) {
                return str((string) $value)->squish()->limit(100, '')->toString();
            }
        }

        $key = $record->getKey();

        return is_scalar($key) ? '#'.((string) $key) : class_basename($record);
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
