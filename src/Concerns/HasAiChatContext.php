<?php

namespace Mbs\LaravelAiChat\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasAiChatContext
{
    public function aiChatLabel(): string
    {
        return class_basename($this);
    }

    public function aiChatDescription(): ?string
    {
        return null;
    }

    /**
     * @return array<int, string>|string
     */
    public function aiChatContextColumns(): array|string
    {
        return 'auto';
    }

    /**
     * @return array<int, string>
     */
    public function aiChatHiddenColumns(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function aiChatContextRelations(): array
    {
        return [];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function aiChatContextQuery(Builder $query): Builder
    {
        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAiChatContext(): array
    {
        return [];
    }
}
