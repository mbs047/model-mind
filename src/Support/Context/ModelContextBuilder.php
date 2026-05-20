<?php

namespace Mbs\ModelMind\Support\Context;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Mbs\ModelMind\Concerns\HasModelMindContext;
use Mbs\ModelMind\Support\Actions\RouteActionRegistry;

class ModelContextBuilder
{
    public function __construct(
        private readonly ModelContextDiscoverer $discoverer,
        private readonly RouteActionRegistry $routeActions,
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
            'label' => $settings['label'] ?? $this->traitLabel($model) ?? class_basename($modelClass),
            'description' => $settings['description'] ?? $this->traitDescription($model),
            'model' => $modelClass,
            'columns' => $columns,
            'route_actions' => $this->routeActionSummaries($model, $settings),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function recordContext(Model $record, array $columns, array $settings): array
    {
        if (in_array(HasModelMindContext::class, class_uses_recursive($record), true)) {
            $custom = $record->toModelMindContext();

            if ($custom !== []) {
                return $this->withRouteActions($record, $settings, $this->cleanArray($custom));
            }
        }

        $context = collect($columns)
            ->mapWithKeys(fn (string $column): array => [$column => $this->cleanValue($record->getAttribute($column))])
            ->reject(fn (mixed $value): bool => $value === null || $value === '' || $value === [])
            ->all();

        return $this->withRouteActions($record, $settings, $context);
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
    private function withRouteActions(Model $record, array $settings, array $context): array
    {
        $actions = $this->routeActions->actionsForRecord($record, $settings);

        if ($actions === []) {
            return $context;
        }

        return [
            ...$context,
            'model_mind_route_actions' => $actions,
        ];
    }
}
