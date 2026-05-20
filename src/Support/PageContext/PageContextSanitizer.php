<?php

namespace Mbs\ModelMind\Support\PageContext;

class PageContextSanitizer
{
    /**
     * @param  array<string, mixed>|null  $context
     * @return array{url?: string, title?: string, description?: string, selection?: string, headings?: array<int, string>, content?: string, locale?: string}
     */
    public function sanitize(?array $context): array
    {
        if (! $this->enabled() || ! is_array($context)) {
            return [];
        }

        $sanitized = array_filter([
            'url' => $this->url($context['url'] ?? null),
            'title' => $this->cleanText($context['title'] ?? null, (int) config('model-mind.page_context.max_title_characters', 180)),
            'description' => $this->cleanText($context['description'] ?? null, (int) config('model-mind.page_context.max_description_characters', 500)),
            'selection' => $this->cleanText($context['selection'] ?? null, (int) config('model-mind.page_context.max_selection_characters', 2000)),
            'locale' => $this->cleanText($context['locale'] ?? null, 30),
        ], fn (?string $value): bool => filled($value));

        $headings = $this->headings($context['headings'] ?? []);

        if ($headings !== []) {
            $sanitized['headings'] = $headings;
        }

        $content = $this->cleanText($context['content'] ?? null, (int) config('model-mind.page_context.max_content_characters', 6000));

        if (filled($content)) {
            $sanitized['content'] = $content;
        }

        return $sanitized;
    }

    private function enabled(): bool
    {
        return (bool) config('model-mind.features.page_context', true)
            && (bool) config('model-mind.page_context.enabled', true);
    }

    private function url(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $url = $this->cleanText((string) $value, 2048);

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    private function cleanText(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = str(strip_tags((string) $value))
            ->replaceMatches('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ')
            ->squish()
            ->limit(max(1, $limit), '')
            ->toString();

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>
     */
    private function headings(mixed $headings): array
    {
        return collect((array) $headings)
            ->map(fn (mixed $heading): ?string => $this->cleanText($heading, (int) config('model-mind.page_context.max_heading_characters', 160)))
            ->filter()
            ->unique()
            ->take(max(0, (int) config('model-mind.page_context.max_headings', 12)))
            ->values()
            ->all();
    }
}
