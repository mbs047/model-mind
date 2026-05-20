<?php

namespace Mbs\ModelMind\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Mbs\ModelMind\Support\Database\TableNames;

class ModelMindMemory extends Model
{
    protected $fillable = [
        'uuid',
        'source',
        'title',
        'content',
        'content_hash',
        'metadata',
        'weight',
        'learned_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (ModelMindMemory $memory): void {
            $memory->uuid ??= (string) Str::uuid();
            $memory->learned_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'learned_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return TableNames::memories();
    }
}
