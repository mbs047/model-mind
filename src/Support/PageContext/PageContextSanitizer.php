<?php

namespace Mbs\ModelMind\Support\PageContext;

class PageContextSanitizer
{
    public function __construct(private readonly PageContextConfig $config) {}

    /**
     * @param  array<string, mixed>|null  $context
     * @return array{url?: string, title?: string, description?: string, selection?: string, headings?: array<int, string>, content?: string, locale?: string}
     */
    public function sanitize(?array $context): array
    {
        if (! $this->config->enabled() || ! is_array($context)) {
            return [];
        }

        $sanitized = array_filter([
            'url' => $this->url($context['url'] ?? null),
            'title' => $this->cleanText($context['title'] ?? null, $this->config->maxTitleCharacters()),
            'description' => $this->cleanText($context['description'] ?? null, $this->config->maxDescriptionCharacters()),
            'selection' => $this->cleanText($context['selection'] ?? null, $this->config->maxSelectionCharacters()),
            'locale' => $this->cleanText($context['locale'] ?? null, 30),
        ], fn (?string $value): bool => filled($value));

        $headings = $this->headings($context['headings'] ?? []);

        if ($headings !== []) {
            $sanitized['headings'] = $headings;
        }

        $content = $this->cleanText($context['content'] ?? null, $this->config->maxContentCharacters());

        if (filled($content)) {
            $sanitized['content'] = $content;
        }

        return $sanitized;
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
            ->map(fn (mixed $heading): ?string => $this->cleanText($heading, $this->config->maxHeadingCharacters()))
            ->filter()
            ->unique()
            ->take($this->config->maxHeadings())
            ->values()
            ->all();
    }
}
