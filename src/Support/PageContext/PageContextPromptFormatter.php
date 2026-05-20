<?php

namespace Mbs\ModelMind\Support\PageContext;

class PageContextPromptFormatter
{
    public function __construct(private readonly PageContextConfig $config) {}

    /**
     * @param  array<string, mixed>  $pageContext
     */
    public function format(array $pageContext): string
    {
        if ($pageContext === []) {
            return '';
        }

        $lines = ['CURRENT PAGE CONTEXT (untrusted browser-visible page snapshot):'];

        foreach ($this->scalarFields() as $key => $label) {
            $line = $this->scalarLine($pageContext[$key] ?? null, $label);

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        $headings = $this->headings($pageContext['headings'] ?? []);

        if ($headings !== []) {
            $lines[] = 'Headings: '.implode(' | ', $headings);
        }

        $selection = $this->block($pageContext['selection'] ?? null, 'Selected text', $this->config->maxSelectionCharacters());

        if ($selection !== null) {
            $lines[] = $selection;
        }

        $content = $this->block($pageContext['content'] ?? null, 'Visible page text', $this->config->maxContentCharacters());

        if ($content !== null) {
            $lines[] = $content;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string>
     */
    private function scalarFields(): array
    {
        return [
            'url' => 'URL',
            'title' => 'Title',
            'description' => 'Description',
            'locale' => 'Locale',
        ];
    }

    private function scalarLine(mixed $value, string $label): ?string
    {
        if (! is_scalar($value) || blank((string) $value)) {
            return null;
        }

        return "{$label}: ".str((string) $value)->squish()->limit(2048, '')->toString();
    }

    /**
     * @return array<int, string>
     */
    private function headings(mixed $headings): array
    {
        return collect((array) $headings)
            ->filter(fn (mixed $heading): bool => is_scalar($heading) && filled((string) $heading))
            ->map(fn (mixed $heading): string => str((string) $heading)->squish()->limit($this->config->maxHeadingCharacters(), '')->toString())
            ->values()
            ->all();
    }

    private function block(mixed $value, string $label, int $limit): ?string
    {
        if (! is_scalar($value) || blank((string) $value)) {
            return null;
        }

        return "{$label}:\n".str((string) $value)->squish()->limit($limit, '')->toString();
    }
}
