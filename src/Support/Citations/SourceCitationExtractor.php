<?php

namespace Mbs\ModelMind\Support\Citations;

use Mbs\ModelMind\Support\Actions\RouteActionRegistry;
use Mbs\ModelMind\Support\Context\ContextRegistry;

class SourceCitationExtractor
{
    public function __construct(
        private readonly ContextRegistry $contextRegistry,
        private readonly RouteActionRegistry $routeActions,
    ) {}

    /**
     * @return array{answer: string, citations: array<int, array{model: string, record: string, source: string, columns: array<int, string>, action: array{label: string, url: string, kind: string}|null}>}
     */
    public function prepare(string $answer, ?string $question = null): array
    {
        if (! $this->enabled()) {
            return [
                'answer' => $answer,
                'citations' => [],
            ];
        }

        $sourceIndex = $this->sourceIndex($question);
        $cleanAnswer = $answer;
        $citations = [];

        foreach ($this->extractSourceTokens($answer) as $sourceToken) {
            $cleanAnswer = $this->removeNeedle($cleanAnswer, $sourceToken['token']);
            $key = $sourceToken['key'];

            if ($key === null || ! isset($sourceIndex[$key])) {
                continue;
            }

            $this->pushCitation($citations, $key, $this->citationForSource(
                $sourceIndex[$key],
                $this->requestedColumns($sourceToken['attributes']['columns'] ?? null),
                $cleanAnswer,
            ));
        }

        $cleanAnswer = $this->cleanAnswer($cleanAnswer);

        if ((bool) config('model-mind.citations.infer_from_answer', true)) {
            foreach ($sourceIndex as $key => $source) {
                if (isset($citations[$key]) || ! $this->answerMentionsSource($cleanAnswer, $source)) {
                    continue;
                }

                $this->pushCitation($citations, $key, $this->citationForSource($source, [], $cleanAnswer));

                if (count($citations) >= $this->maxCitations()) {
                    break;
                }
            }
        }

        return [
            'answer' => $cleanAnswer,
            'citations' => array_slice(array_values($citations), 0, $this->maxCitations()),
        ];
    }

    /**
     * @return array<string, array{key: string, model: string, record: string, source: string, columns: array<int, string>, values: array<string, string>, route_actions: array<int, array<string, mixed>>}>
     */
    private function sourceIndex(?string $question): array
    {
        $context = $this->contextRegistry->context($question);
        $models = [
            ...$this->modelsFromContext($context),
            ...$this->modelsFromContext($context['question_context'] ?? []),
        ];
        $sources = [];

        foreach ($models as $modelContext) {
            $modelLabel = $this->cleanLabel($modelContext['label'] ?? $modelContext['model'] ?? 'Model');

            foreach ((array) ($modelContext['rows'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $source = $this->sourceFromRow($row, $modelLabel);

                if ($source === null) {
                    continue;
                }

                $sources[$source['key']] = $source;
            }
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    private function modelsFromContext(mixed $context): array
    {
        if (! is_array($context) || ! is_array($context['models'] ?? null)) {
            return [];
        }

        return collect($context['models'])
            ->filter(fn (mixed $model): bool => is_array($model))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{key: string, model: string, record: string, source: string, columns: array<int, string>, values: array<string, string>, route_actions: array<int, array<string, mixed>>}|null
     */
    private function sourceFromRow(array $row, string $modelLabel): ?array
    {
        $metadata = $row['model_mind_source'] ?? null;

        if (! is_array($metadata)) {
            return null;
        }

        $key = $this->cleanKey($metadata['key'] ?? '');

        if ($key === '') {
            return null;
        }

        $columns = collect((array) ($metadata['columns'] ?? []))
            ->filter(fn (mixed $column): bool => is_string($column) && $this->cleanColumn($column) !== '')
            ->map(fn (string $column): string => $this->cleanColumn($column))
            ->unique()
            ->values()
            ->all();

        if ($columns === []) {
            $columns = $this->rowColumns($row);
        }

        $values = [];

        foreach ($columns as $column) {
            $value = $row[$column] ?? null;

            if (is_scalar($value) && filled((string) $value)) {
                $values[$column] = str((string) $value)->squish()->limit(300, '')->toString();
            }
        }

        $record = $this->cleanLabel($metadata['record'] ?? null);
        $model = $this->cleanLabel($metadata['model'] ?? $modelLabel);

        return [
            'key' => $key,
            'model' => $model !== '' ? $model : $modelLabel,
            'record' => $record !== '' ? $record : '#'.$key,
            'source' => sprintf('%s: %s', $model !== '' ? $model : $modelLabel, $record !== '' ? $record : '#'.$key),
            'columns' => $columns,
            'values' => $values,
            'route_actions' => $this->routeActionsFromRow($row),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function rowColumns(array $row): array
    {
        return collect($row)
            ->keys()
            ->filter(fn (mixed $column): bool => is_string($column)
                && ! str_starts_with($column, 'model_mind_')
                && $this->cleanColumn($column) !== '')
            ->map(fn (string $column): string => $this->cleanColumn($column))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array<string, mixed>>
     */
    private function routeActionsFromRow(array $row): array
    {
        return collect((array) ($row['model_mind_route_actions'] ?? []))
            ->filter(fn (mixed $action): bool => is_array($action))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{token: string, key: string|null, attributes: array<string, string>}>
     */
    private function extractSourceTokens(string $answer): array
    {
        $token = preg_quote($this->token(), '~');
        preg_match_all("~\\[\\[{$token}\\s+([^\\]]+)\\]\\]~iu", $answer, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(function (array $match): array {
                $attributes = $this->parseAttributes((string) ($match[1] ?? ''));
                $key = $this->cleanKey($attributes['key'] ?? '');

                return [
                    'token' => (string) ($match[0] ?? ''),
                    'key' => $key !== '' ? $key : null,
                    'attributes' => $attributes,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function parseAttributes(string $source): array
    {
        preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/u', $source, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->mapWithKeys(function (array $match): array {
                $name = (string) ($match[1] ?? '');
                $value = (string) (($match[2] ?? '') !== ''
                    ? $match[2]
                    : (($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? '')));

                return [$name => str($value)->squish()->limit(240, '')->toString()];
            })
            ->all();
    }

    /**
     * @param  array{key: string, model: string, record: string, source: string, columns: array<int, string>, values: array<string, string>, route_actions: array<int, array<string, mixed>>}  $source
     * @param  array<int, string>  $requestedColumns
     * @return array{model: string, record: string, source: string, columns: array<int, string>, action: array{label: string, url: string, kind: string}|null}
     */
    private function citationForSource(array $source, array $requestedColumns, string $answer): array
    {
        return [
            'model' => $source['model'],
            'record' => $source['record'],
            'source' => $source['source'],
            'columns' => $this->columnsForSource($source, $requestedColumns, $answer),
            'action' => $this->actionForSource($source),
        ];
    }

    /**
     * @param  array{columns: array<int, string>, values: array<string, string>}  $source
     * @param  array<int, string>  $requestedColumns
     * @return array<int, string>
     */
    private function columnsForSource(array $source, array $requestedColumns, string $answer): array
    {
        $availableColumns = array_values(array_intersect($source['columns'], array_keys($source['values'])));
        $columns = collect($requestedColumns)
            ->filter(fn (string $column): bool => in_array($column, $availableColumns, true))
            ->values()
            ->all();

        if ($columns === []) {
            $normalizedAnswer = $this->normalizeForMatching($answer);
            $columns = collect($source['values'])
                ->filter(fn (string $value): bool => $this->phraseAppearsInAnswer($normalizedAnswer, $value))
                ->keys()
                ->values()
                ->all();
        }

        if ($columns === []) {
            $columns = $availableColumns;
        }

        return collect($columns)
            ->unique()
            ->take($this->maxColumns())
            ->values()
            ->all();
    }

    /**
     * @param  array{route_actions: array<int, array<string, mixed>>}  $source
     * @return array{label: string, url: string, kind: string}|null
     */
    private function actionForSource(array $source): ?array
    {
        foreach ($source['route_actions'] as $routeAction) {
            $token = $routeAction['token'] ?? null;

            if (! is_string($token) || blank($token)) {
                continue;
            }

            $attributes = $this->attributesFromRouteToken($token);
            $key = $attributes['key'] ?? null;

            if (! is_string($key) || blank($key)) {
                continue;
            }

            unset($attributes['key']);
            $label = is_string($attributes['label'] ?? null) ? $attributes['label'] : null;
            unset($attributes['label']);

            $action = $this->routeActions->resolve($key, $attributes, $label);

            if ($action !== null) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function attributesFromRouteToken(string $routeToken): array
    {
        $token = preg_quote($this->routeActions->token(), '~');

        if (preg_match("~\\[\\[{$token}\\s+([^\\]]+)\\]\\]~iu", $routeToken, $match) !== 1) {
            return [];
        }

        return $this->parseAttributes((string) ($match[1] ?? ''));
    }

    /**
     * @param  array{record: string, values: array<string, string>}  $source
     */
    private function answerMentionsSource(string $answer, array $source): bool
    {
        $normalizedAnswer = $this->normalizeForMatching($answer);

        if ($normalizedAnswer === '') {
            return false;
        }

        $phrases = [
            $source['record'],
            ...array_values($source['values']),
        ];

        foreach ($phrases as $phrase) {
            if ($this->phraseAppearsInAnswer($normalizedAnswer, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function phraseAppearsInAnswer(string $normalizedAnswer, string $phrase): bool
    {
        $normalizedPhrase = $this->normalizeForMatching($phrase);

        return mb_strlen($normalizedPhrase) >= 4
            && ! preg_match('/^\d+(?:\s+\d+)*$/', $normalizedPhrase)
            && str_contains(" {$normalizedAnswer} ", " {$normalizedPhrase} ");
    }

    /**
     * @return array<int, string>
     */
    private function requestedColumns(mixed $columns): array
    {
        if (! is_scalar($columns)) {
            return [];
        }

        return str((string) $columns)
            ->replace(['|', ';'], ',')
            ->explode(',')
            ->map(fn (string $column): string => $this->cleanColumn(trim($column)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array{model: string, record: string, source: string, columns: array<int, string>, action: array{label: string, url: string, kind: string}|null}>  $citations
     * @param  array{model: string, record: string, source: string, columns: array<int, string>, action: array{label: string, url: string, kind: string}|null}  $citation
     */
    private function pushCitation(array &$citations, string $key, array $citation): void
    {
        if (blank($citation['model']) || blank($citation['record'])) {
            return;
        }

        $citations[$key] ??= $citation;
    }

    private function removeNeedle(string $text, string $needle): string
    {
        return preg_replace('~\s*(?:[:\-–—]\s*)?'.preg_quote($needle, '~').'~u', '', $text) ?? $text;
    }

    private function cleanAnswer(string $answer): string
    {
        return str($answer)
            ->replaceMatches('~\s+([.,!?;:])~u', '$1')
            ->replaceMatches('~\s{2,}~u', ' ')
            ->trim(" \t\n\r\0\x0B:-")
            ->toString();
    }

    private function enabled(): bool
    {
        return (bool) config('model-mind.features.citations', true)
            && (bool) config('model-mind.citations.enabled', true)
            && $this->maxCitations() > 0;
    }

    private function maxCitations(): int
    {
        return max(0, (int) config('model-mind.citations.max_citations', 4));
    }

    private function maxColumns(): int
    {
        return max(1, (int) config('model-mind.citations.max_columns', 4));
    }

    private function token(): string
    {
        $token = config('model-mind.citations.token', 'model_mind_source');

        return is_string($token) && preg_match('/^[A-Za-z][A-Za-z0-9_:-]*$/', $token) === 1
            ? $token
            : 'model_mind_source';
    }

    private function cleanKey(mixed $key): string
    {
        if (! is_scalar($key)) {
            return '';
        }

        return str((string) $key)
            ->squish()
            ->replaceMatches('/[^A-Za-z0-9_.:-]/', '')
            ->limit(100, '')
            ->toString();
    }

    private function cleanColumn(string $column): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) === 1 ? $column : '';
    }

    private function cleanLabel(mixed $label): string
    {
        if (! is_scalar($label)) {
            return '';
        }

        return str(strip_tags((string) $label))->squish()->limit(100, '')->toString();
    }

    private function normalizeForMatching(string $value): string
    {
        return str($value)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->squish()
            ->toString();
    }
}
