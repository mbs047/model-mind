<?php

namespace Mbs\ModelMind\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Mbs\ModelMind\Support\Database\TableNames;

class ModelMindMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const FEEDBACK_LIKED = 'liked';

    public const FEEDBACK_DISLIKED = 'disliked';

    protected $fillable = [
        'model_mind_session_id',
        'uuid',
        'role',
        'content',
        'metadata',
        'feedback',
        'feedback_note',
        'feedback_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (ModelMindMessage $message): void {
            $message->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'feedback_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function getTable(): string
    {
        return TableNames::messages();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ModelMindSession::class, 'model_mind_session_id');
    }

    public function isAssistant(): bool
    {
        return $this->role === self::ROLE_ASSISTANT;
    }
}
