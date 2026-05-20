<?php

namespace Mbs\ModelMind\Support\PageContext;

class PageContextConfig
{
    /**
     * @return array{enabled: bool, maxContentCharacters: int, maxSelectionCharacters: int, selectors: array<int, string>, excludeSelectors: array<int, string>}
     */
    public function frontend(): array
    {
        return [
            'enabled' => $this->enabled(),
            'maxContentCharacters' => $this->maxContentCharacters(),
            'maxSelectionCharacters' => $this->maxSelectionCharacters(),
            'selectors' => $this->selectors('model-mind.page_context.selectors'),
            'excludeSelectors' => $this->selectors('model-mind.page_context.exclude_selectors'),
        ];
    }

    public function enabled(): bool
    {
        return (bool) config('model-mind.features.page_context', true)
            && (bool) config('model-mind.page_context.enabled', true);
    }

    public function maxContentCharacters(): int
    {
        return $this->positiveInt('model-mind.page_context.max_content_characters', 6000);
    }

    public function maxSelectionCharacters(): int
    {
        return $this->positiveInt('model-mind.page_context.max_selection_characters', 2000);
    }

    public function maxTitleCharacters(): int
    {
        return $this->positiveInt('model-mind.page_context.max_title_characters', 180);
    }

    public function maxDescriptionCharacters(): int
    {
        return $this->positiveInt('model-mind.page_context.max_description_characters', 500);
    }

    public function maxHeadingCharacters(): int
    {
        return $this->positiveInt('model-mind.page_context.max_heading_characters', 160);
    }

    public function maxHeadings(): int
    {
        return max(0, (int) config('model-mind.page_context.max_headings', 12));
    }

    private function positiveInt(string $key, int $fallback): int
    {
        return max(1, (int) config($key, $fallback));
    }

    /**
     * @return array<int, string>
     */
    private function selectors(string $key): array
    {
        return collect(config($key, []))
            ->filter(fn (mixed $selector): bool => is_string($selector) && filled($selector))
            ->values()
            ->all();
    }
}
