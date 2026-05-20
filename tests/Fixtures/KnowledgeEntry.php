<?php

namespace Mbs\ModelMind\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mbs\ModelMind\Concerns\HasModelMindContext;

class KnowledgeEntry extends Model
{
    use HasModelMindContext;

    protected $table = 'model_mind_knowledge_entries';

    protected $guarded = [];

    protected $hidden = [
        'hidden_note',
    ];

    public function modelMindLabel(): string
    {
        return 'Knowledge entries';
    }

    public function modelMindDescription(): ?string
    {
        return 'Approved public knowledge for the assistant.';
    }

    /**
     * @return array<int, string>
     */
    public function modelMindHiddenColumns(): array
    {
        return [
            'internal_note',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function modelMindContextQuery(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }
}
