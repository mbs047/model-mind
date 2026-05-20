<?php

namespace Mbs\ModelMind\Support\Learning;

use Illuminate\Support\Facades\Cache;
use Mbs\ModelMind\Models\ModelMindMemory;
use Mbs\ModelMind\Models\ModelMindMessage;

class LearningRepository
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function remember(string $content, string $source = 'manual', ?string $title = null, array $metadata = [], int $weight = 1): ?ModelMindMemory
    {
        if (! $this->enabled()) {
            return null;
        }

        $content = $this->cleanContent($content);

        if ($content === '' || mb_strlen($content) < (int) config('model-mind.learning.min_characters', 40) || $this->looksSensitive($content)) {
            return null;
        }

        $source = str($source)->slug('_')->limit(80, '')->toString() ?: 'manual';
        $title = $title ? str($title)->squish()->limit(120, '')->toString() : null;
        $hash = hash('sha256', mb_strtolower($source.'|'.$content));
        $memory = ModelMindMemory::query()->where('content_hash', $hash)->first();

        if ($memory) {
            $memory->forceFill([
                'title' => $title ?? $memory->title,
                'metadata' => array_filter([
                    ...($memory->metadata ?? []),
                    ...$metadata,
                ]),
                'weight' => max((int) $memory->weight, $weight),
                'learned_at' => now(),
            ])->save();
        } else {
            $memory = ModelMindMemory::query()->create([
                'source' => $source,
                'title' => $title,
                'content' => $content,
                'content_hash' => $hash,
                'metadata' => array_filter($metadata),
                'weight' => max(1, min($weight, 100)),
                'learned_at' => now(),
            ]);
        }

        Cache::forget('model-mind.context.v1');

        return $memory;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function rememberAssistantAnswer(string $answer, array $metadata = []): ?ModelMindMemory
    {
        if (! (bool) config('model-mind.learning.from_assistant_answers', true)) {
            return null;
        }

        if (str($answer)->squish()->toString() === str((string) config('model-mind.assistant.fallback_answer'))->squish()->toString()) {
            return null;
        }

        return $this->remember($answer, 'assistant_answer', 'Assistant answer', $metadata, 2);
    }

    public function rememberLikedAnswer(ModelMindMessage $message): ?ModelMindMemory
    {
        if (! (bool) config('model-mind.learning.from_liked_answers', true) || ! $message->isAssistant()) {
            return null;
        }

        return $this->remember($message->content, 'liked_answer', 'Liked assistant answer', [
            'message_id' => $message->uuid,
            'session_id' => $message->session?->uuid,
        ], 6);
    }

    /**
     * @return array<int, array{title: string|null, content: string, source: string}>
     */
    public function fedTextContext(): array
    {
        if (! $this->enabled() || ! (bool) config('model-mind.learning.from_fed_texts', true)) {
            return [];
        }

        return collect(config('model-mind.learning.fed_texts', []))
            ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['content'] ?? null))
            ->take(max(0, (int) config('model-mind.learning.fed_text_limit', 20)))
            ->map(function (array $item): array {
                return [
                    'title' => is_string($item['title'] ?? null) ? str($item['title'])->squish()->limit(120, '')->toString() : null,
                    'content' => $this->cleanContent((string) $item['content']),
                    'source' => is_string($item['source'] ?? null) ? str($item['source'])->slug('_')->limit(80, '')->toString() : 'fed_text',
                ];
            })
            ->reject(fn (array $item): bool => $item['content'] === '' || $this->looksSensitive($item['content']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{title: string|null, content: string, source: string, weight: int}>
     */
    public function learnedContext(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return ModelMindMemory::query()
            ->latest('weight')
            ->latest('learned_at')
            ->limit(max(0, (int) config('model-mind.learning.context_limit', 12)))
            ->get(['title', 'content', 'source', 'weight'])
            ->map(fn (ModelMindMemory $memory): array => [
                'title' => $memory->title,
                'content' => $memory->content,
                'source' => $memory->source,
                'weight' => (int) $memory->weight,
            ])
            ->all();
    }

    private function enabled(): bool
    {
        return (bool) config('model-mind.learning.enabled', true);
    }

    private function cleanContent(string $content): string
    {
        if ((bool) config('model-mind.security.strip_html', true)) {
            $content = strip_tags($content);
        }

        return str($content)
            ->squish()
            ->limit((int) config('model-mind.learning.learned_text_characters', 1200), '')
            ->toString();
    }

    private function looksSensitive(string $content): bool
    {
        foreach ((array) config('model-mind.learning.blocked_patterns', []) as $pattern) {
            if (is_string($pattern) && @preg_match($pattern, $content) === 1) {
                return true;
            }
        }

        return false;
    }
}
