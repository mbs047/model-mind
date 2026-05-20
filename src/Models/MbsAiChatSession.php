<?php

namespace Mbs\LaravelAiChat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MbsAiChatSession extends Model
{
    protected $table = 'mbs_ai_chat_sessions';

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
        static::creating(function (MbsAiChatSession $session): void {
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
        return $this->hasMany(MbsAiChatMessage::class)->oldest('id');
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

    public function compactForPrompt(): void
    {
        $recentLimit = (int) config('mbs-ai-chat.memory.recent_messages', 12);
        $summaryLimit = (int) config('mbs-ai-chat.memory.summary_characters', 3000);
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
     * @return Collection<int, MbsAiChatMessage>
     */
    public function recentMessagesForPrompt(): Collection
    {
        $recentLimit = (int) config('mbs-ai-chat.memory.recent_messages', 12);

        return $this->messages()
            ->reorder()
            ->latest('id')
            ->limit($recentLimit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @param  Collection<int, MbsAiChatMessage>  $olderMessages
     */
    private function buildConversationSummary(Collection $olderMessages, int $summaryLimit): string
    {
        $previousSummary = str($this->conversation_summary ?? '')->squish()->toString();
        $olderDigest = $olderMessages
            ->map(fn (MbsAiChatMessage $message): string => sprintf(
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
