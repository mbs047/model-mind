<?php

namespace Mbs\ModelMind\Support\Actions;

class ActionExtractor
{
    public function __construct(private readonly RouteActionRegistry $routeActions) {}

    /**
     * @return array{answer: string, actions: array<int, array{label: string, url: string, kind: string}>}
     */
    public function prepare(string $answer): array
    {
        $actions = [];
        $cleanAnswer = $answer;

        foreach ($this->extractRouteActions($answer) as $routeAction) {
            $this->pushAction($actions, $routeAction['action']);
            $cleanAnswer = $this->removeNeedle($cleanAnswer, $routeAction['token']);
        }

        foreach ($this->routeActions->inferredActionsForAnswer($cleanAnswer) as $action) {
            $this->pushAction($actions, $action);
        }

        foreach ($this->extractUrls($answer) as $url) {
            $this->pushAction($actions, $this->actionForUrl($url));
            $cleanAnswer = $this->removeNeedle($cleanAnswer, $url);
        }

        foreach ($this->extractEmails($answer) as $email) {
            $this->pushAction($actions, [
                'label' => 'Email',
                'url' => "mailto:{$email}",
                'kind' => 'email',
            ]);
            $cleanAnswer = $this->removeNeedle($cleanAnswer, $email);
        }

        return [
            'answer' => $this->cleanAnswer($cleanAnswer),
            'actions' => array_slice(array_values($actions), 0, max(0, (int) config('model-mind.actions.max_actions', 5))),
        ];
    }

    /**
     * @return array<int, array{token: string, action: array{label: string, url: string, kind: string}|null}>
     */
    private function extractRouteActions(string $answer): array
    {
        $token = preg_quote($this->routeActions->token(), '~');
        preg_match_all("~\\[\\[{$token}\\s+([^\\]]+)\\]\\]~iu", $answer, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(function (array $match): ?array {
                $attributes = $this->parseAttributes((string) ($match[1] ?? ''));
                $key = $attributes['key'] ?? null;

                if (! is_string($key) || blank($key)) {
                    return [
                        'token' => (string) ($match[0] ?? ''),
                        'action' => null,
                    ];
                }

                unset($attributes['key']);
                $label = is_string($attributes['label'] ?? null) ? $attributes['label'] : null;
                unset($attributes['label']);

                return [
                    'token' => (string) ($match[0] ?? ''),
                    'action' => $this->routeActions->resolve($key, $attributes, $label),
                ];
            })
            ->filter()
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

                return [$name => str($value)->squish()->limit(200, '')->toString()];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractUrls(string $answer): array
    {
        preg_match_all('~https?://[^\s<>"\']+~iu', $answer, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $url): string => rtrim($url, '.,;:!?)]}'))
            ->unique(fn (string $url): string => strtolower(rtrim($url, '/')))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractEmails(string $answer): array
    {
        preg_match_all('~[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}~iu', $answer, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $email): string => strtolower($email))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array{label: string, url: string, kind: string}>  $actions
     * @param  array{label: string, url: string, kind: string}|null  $action
     */
    private function pushAction(array &$actions, ?array $action): void
    {
        if ($action === null || blank($action['url']) || blank($action['label'])) {
            return;
        }

        $key = strtolower(rtrim($action['url'], '/'));
        $actions[$key] ??= $action;
    }

    /**
     * @return array{label: string, url: string, kind: string}|null
     */
    private function actionForUrl(string $url): ?array
    {
        if (str($url)->contains('linkedin.com')) {
            return ['label' => 'LinkedIn', 'url' => $url, 'kind' => 'linkedin'];
        }

        if (str($url)->contains('github.com')) {
            return ['label' => 'GitHub', 'url' => $url, 'kind' => 'github'];
        }

        return ['label' => 'Open link', 'url' => $url, 'kind' => 'link'];
    }

    private function removeNeedle(string $text, string $needle): string
    {
        return preg_replace('~\s*(?:[:\-–—]\s*)?'.preg_quote($needle, '~').'~u', '', $text) ?? $text;
    }

    private function cleanAnswer(string $answer): string
    {
        $cleaned = str($answer)
            ->replaceMatches('~\s+([.,!?;:])~u', '$1')
            ->replaceMatches('~\b(at|on|via|using)\s+(?=(or|and|[.,;:]|$))~iu', '')
            ->replaceMatches('~\b(at|on|via|using)(?=\s*[.,;:]|$)~iu', '')
            ->replaceMatches('~\s+([.,!?;:])~u', '$1')
            ->replaceMatches('~\s{2,}~u', ' ')
            ->trim(" \t\n\r\0\x0B:-")
            ->toString();

        return filled($cleaned)
            ? $cleaned
            : (string) config('model-mind.assistant.fallback_answer', 'I do not have that information in the enabled application context yet.');
    }
}
