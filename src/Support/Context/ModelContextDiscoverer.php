<?php

namespace Mbs\LaravelAiChat\Support\Context;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Mbs\LaravelAiChat\Concerns\HasAiChatContext;

class ModelContextDiscoverer
{
    public function __construct(private readonly SensitiveColumnFilter $filter) {}

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    public function columns(Model $model, array $settings = []): array
    {
        $traitColumns = $this->traitColumns($model);
        $configuredColumns = $settings['columns'] ?? $traitColumns ?? 'auto';
        $include = array_values(array_filter((array) ($settings['include'] ?? []), 'is_string'));
        $exclude = array_values(array_filter(array_merge(
            (array) ($settings['exclude'] ?? []),
            $this->traitHiddenColumns($model),
        ), 'is_string'));

        if ($configuredColumns !== 'auto') {
            return collect((array) $configuredColumns)
                ->filter(fn (mixed $column): bool => is_string($column) && $this->filter->allowed($column, $model->getCasts()[$column] ?? null, $include, $exclude))
                ->values()
                ->all();
        }

        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            return [];
        }

        return collect(Schema::getColumnListing($table))
            ->reject(fn (string $column): bool => in_array($column, $model->getHidden(), true))
            ->when($model->getVisible() !== [], fn ($columns) => $columns->filter(fn (string $column): bool => in_array($column, $model->getVisible(), true)))
            ->filter(fn (string $column): bool => $this->filter->allowed($column, $model->getCasts()[$column] ?? null, $include, $exclude))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>|string|null
     */
    private function traitColumns(Model $model): array|string|null
    {
        if (! in_array(HasAiChatContext::class, class_uses_recursive($model), true)) {
            return null;
        }

        return $model->aiChatContextColumns();
    }

    /**
     * @return array<int, string>
     */
    private function traitHiddenColumns(Model $model): array
    {
        if (! in_array(HasAiChatContext::class, class_uses_recursive($model), true)) {
            return [];
        }

        return $model->aiChatHiddenColumns();
    }
}
