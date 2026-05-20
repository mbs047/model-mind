<?php

namespace Mbs\ModelMind\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Mbs\ModelMind\Support\Database\TableNames;

class ModelMindEvent extends Model
{
    public const TYPE_CHAT_COMPLETED = 'chat.completed';

    public const TYPE_CHAT_FAILED = 'chat.failed';

    public const TYPE_FEEDBACK_SUBMITTED = 'feedback.submitted';

    public const TYPE_ACTION_CLICKED = 'action.clicked';

    protected $fillable = [
        'model_mind_session_id',
        'model_mind_message_id',
        'uuid',
        'type',
        'provider',
        'provider_model',
        'latency_ms',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'metadata',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (ModelMindEvent $event): void {
            $event->uuid ??= (string) Str::uuid();
            $event->occurred_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function getTable(): string
    {
        return TableNames::events();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ModelMindSession::class, 'model_mind_session_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ModelMindMessage::class, 'model_mind_message_id');
    }
}
