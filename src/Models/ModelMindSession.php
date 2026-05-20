<?php

namespace Mbs\ModelMind\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mbs\ModelMind\Support\Database\TableNames;

class ModelMindSession extends Model
{
    protected $fillable = [
        'uuid',
        'conversation_summary',
        'message_count',
        'compacted_message_count',
        'compacted_at',
        'last_interaction_at',
        'ip_address',
        'user_agent',
    ];

    protected static function booted(): void
    {
        static::creating(function (ModelMindSession $session): void {
            $session->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'compacted_at' => 'datetime',
            'last_interaction_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ModelMindMessage::class)->oldest('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ModelMindEvent::class, 'model_mind_session_id')->oldest('id');
    }

    public function getTable(): string
    {
        return TableNames::sessions();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function touchInteraction(): void
    {
        $this->forceFill([
            'last_interaction_at' => now(),
            'message_count' => $this->messages()->count(),
        ])->save();
    }

    public function markInteraction(): void
    {
        $this->forceFill([
            'last_interaction_at' => now(),
        ])->save();
    }

    public function compactForPrompt(): void
    {
        $recentLimit = (int) config('model-mind.memory.recent_messages', 12);
        $summaryLimit = (int) config('model-mind.memory.summary_characters', 3000);
        $messageCount = $this->messages()->count();
        $olderMessageCount = max(0, $messageCount - $recentLimit);
        $alreadyCompactedCount = min((int) $this->compacted_message_count, $olderMessageCount);

        if ($messageCount <= $recentLimit || $olderMessageCount <= $alreadyCompactedCount) {
            $this->touchInteraction();

            return;
        }

        $olderMessages = $this->messages()
            ->reorder()
            ->oldest()
            ->skip($alreadyCompactedCount)
            ->limit($olderMessageCount - $alreadyCompactedCount)
            ->get(['role', 'content', 'feedback']);

        $summary = $this->buildConversationSummary($olderMessages, $summaryLimit);

        $this->forceFill([
            'conversation_summary' => $summary,
            'message_count' => $messageCount,
            'compacted_message_count' => $olderMessageCount,
            'compacted_at' => now(),
            'last_interaction_at' => now(),
        ])->save();
    }

    /**
     * @return Collection<int, ModelMindMessage>
     */
    public function recentMessagesForPrompt(): Collection
    {
        $recentLimit = (int) config('model-mind.memory.recent_messages', 12);

        return $this->messages()
            ->reorder()
            ->latest('id')
            ->limit($recentLimit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @param  Collection<int, ModelMindMessage>  $olderMessages
     */
    private function buildConversationSummary(Collection $olderMessages, int $summaryLimit): string
    {
        $previousSummary = str($this->conversation_summary ?? '')->squish()->toString();
        $olderDigest = $olderMessages
            ->map(fn (ModelMindMessage $message): string => sprintf(
                '%s%s: %s',
                $message->isAssistant() ? 'Assistant' : 'Visitor',
                $message->feedback ? " ({$message->feedback})" : '',
                str($message->content)->squish()->limit(320, '')->toString(),
            ))
            ->implode(' | ');

        return str($olderDigest."\n".$previousSummary)
            ->squish()
            ->limit($summaryLimit, '')
            ->toString();
    }
}
