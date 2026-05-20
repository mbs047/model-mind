<?php

namespace Mbs\ModelMind\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasModelMindContext
{
    public function modelMindLabel(): string
    {
        return class_basename($this);
    }

    public function modelMindDescription(): ?string
    {
        return null;
    }

    /**
     * @return array<int, string>|string
     */
    public function modelMindContextColumns(): array|string
    {
        return 'auto';
    }

    /**
     * @return array<int, string>
     */
    public function modelMindHiddenColumns(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function modelMindContextRelations(): array
    {
        return [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function modelMindRouteActions(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function modelMindAuthorization(): array
    {
        return [];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function modelMindContextQuery(Builder $query): Builder
    {
        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function toModelMindContext(): array
    {
        return [];
    }
}
