@php
    $assistant = config('model-mind.assistant');
    $configuredQuickQuestions = $quickQuestions ?? $assistant['default_questions'] ?? $assistant['quick_questions'] ?? [];
    $configuredQuickQuestions = is_string($configuredQuickQuestions)
        ? explode('|', $configuredQuickQuestions)
        : (array) $configuredQuickQuestions;
    $configuredQuickQuestions = collect($configuredQuickQuestions)
        ->filter(fn (mixed $question): bool => is_scalar($question))
        ->map(fn (mixed $question): string => str(strip_tags((string) $question))->squish()->limit(90, '')->toString())
        ->filter()
        ->take(6)
        ->values()
        ->all();
    $modelMindConfig = [
        'endpoint' => route(config('model-mind.routes.name', 'model-mind.').'chat'),
        'streamEndpoint' => route(config('model-mind.routes.name', 'model-mind.').'stream'),
        'sessionEndpoint' => route(config('model-mind.routes.name', 'model-mind.').'session'),
        'feedbackEndpoint' => url(config('model-mind.routes.prefix', 'model-mind').'/messages'),
        'csrfToken' => csrf_token(),
        'initialMessage' => $assistant['initial_message'] ?? null,
        'quickQuestions' => $configuredQuickQuestions,
        'fallbackAnswer' => $assistant['fallback_answer'] ?? null,
        'storageKey' => config('model-mind.ui.storage_key', 'model-mind-state'),
        'browserMessages' => (int) config('model-mind.memory.browser_messages', 60),
        'historyMessages' => (int) config('model-mind.memory.recent_messages', 12),
        'sessionLifetimeMinutes' => max(0, (int) config('model-mind.memory.session_lifetime_minutes', 120)),
        'feedbackEnabled' => (bool) config('model-mind.features.feedback', true),
        'streamingEnabled' => (bool) config('model-mind.features.streaming', false),
    ];
    $ui = config('model-mind.ui', []);
    $normalizeCssLength = function (mixed $value, string $fallback): string {
        if (! is_numeric($value) && ! is_string($value)) {
            return $fallback;
        }

        $value = trim((string) $value);

        return preg_match('/^\d*\.?\d+(px|rem|em|vh|vw|%)$/', $value) === 1 ? $value : $fallback;
    };
    $position = strtolower((string) ($ui['position'] ?? 'bottom-right'));
    $position = preg_replace('/[\s_]+/', '-', $position) ?: 'bottom-right';
    $positionAliases = [
        'bottom' => 'bottom-center',
        'center-bottom' => 'bottom-center',
        'center-left' => 'center-left',
        'center-right' => 'center-right',
        'left' => 'center-left',
        'middle' => 'center',
        'middle-left' => 'center-left',
        'middle-right' => 'center-right',
        'right' => 'center-right',
        'top' => 'top-center',
        'center-top' => 'top-center',
    ];
    $position = $positionAliases[$position] ?? $position;
    $supportedPositions = [
        'bottom-center',
        'bottom-left',
        'bottom-right',
        'center',
        'center-left',
        'center-right',
        'top-center',
        'top-left',
        'top-right',
    ];
    $position = in_array($position, $supportedPositions, true) ? $position : 'bottom-right';
    $theme = strtolower((string) ($ui['theme'] ?? 'auto'));
    $theme = in_array($theme, ['auto', 'light', 'dark'], true) ? $theme : 'auto';
    $modelMindConfig['theme'] = $theme;
    $width = $normalizeCssLength($ui['width'] ?? null, '25rem');
    $offset = $normalizeCssLength($ui['offset'] ?? null, '1.25rem');
    $zIndex = max(1, min((int) ($ui['z_index'] ?? 9999), 2147483647));
@endphp

<div
    class="model-mind-widget {{ $theme === 'dark' ? 'dark' : '' }}"
    style="--model-mind-width: {{ $width }}; --model-mind-offset: {{ $offset }}; --model-mind-z-index: {{ $zIndex }};"
    data-model-mind-position="{{ $position }}"
    data-model-mind-theme="{{ $theme }}"
    data-model-mind-widget
>
    <script type="application/json" data-model-mind-config>{!! json_encode($modelMindConfig, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) !!}</script>

    <div
        id="model-mind-panel"
        class="mb-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15 dark:border-white/10 dark:bg-slate-950"
        role="dialog"
        aria-modal="false"
        aria-labelledby="model-mind-title"
        data-model-mind-panel
        hidden
    >
        <div class="border-b border-slate-200 bg-slate-950 p-4 text-white dark:border-white/10">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex size-10 items-center justify-center rounded-xl bg-white text-sm font-black text-slate-950">{{ $assistant['brand_mark'] ?? 'MBS' }}</span>
                    <div>
                        <p id="model-mind-title" class="text-sm font-bold">{{ $assistant['name'] ?? 'ModelMind' }}</p>
                        <p class="text-xs font-medium text-slate-300">{{ $assistant['subtitle'] ?? 'AI assistant powered by your application data' }}</p>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-full border border-white/10 text-white/75 transition hover:bg-white/10 hover:text-white"
                    aria-label="Close ModelMind"
                    data-model-mind-close
                >
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        </div>

        <div
            class="max-h-[24rem] space-y-3 overflow-y-auto bg-slate-50 p-4 dark:bg-slate-900"
            data-model-mind-messages
        ></div>

        <div class="border-t border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950">
            <div class="mb-3 flex flex-wrap gap-2" data-model-mind-quick-questions></div>

            <form class="flex items-end gap-2" data-model-mind-form>
                <label for="model-mind-question" class="sr-only">Ask ModelMind</label>
                <textarea
                    id="model-mind-question"
                    rows="1"
                    maxlength="2000"
                    class="min-h-11 flex-1 resize-none rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-slate-950 focus:ring-4 focus:ring-slate-950/10 disabled:opacity-60 dark:border-white/10 dark:bg-slate-900 dark:text-white dark:focus:border-white"
                    placeholder="{{ $assistant['placeholder'] ?? 'Ask about the enabled application data' }}"
                    data-model-mind-draft
                ></textarea>

                <button
                    type="submit"
                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-sm font-black text-white shadow-lg shadow-slate-950/15 transition hover:scale-105 disabled:pointer-events-none disabled:opacity-50 dark:bg-white dark:text-slate-950"
                    aria-label="Send question"
                    data-model-mind-submit
                    disabled
                >&#8593;</button>
            </form>

            <p class="mt-3 text-sm font-bold text-rose-600" role="alert" data-model-mind-failure hidden></p>
        </div>
    </div>

    <button
        type="button"
        class="flex items-center gap-3 rounded-full border border-slate-200 bg-white px-4 py-3 text-slate-950 shadow-xl shadow-slate-900/10 transition hover:-translate-y-0.5 hover:border-slate-400 dark:border-white/10 dark:bg-slate-950 dark:text-white"
        aria-expanded="false"
        aria-controls="model-mind-panel"
        data-model-mind-toggle
    >
        <span class="inline-flex size-9 items-center justify-center rounded-full bg-slate-950 text-[0.65rem] font-black text-white dark:bg-white dark:text-slate-950">{{ $assistant['brand_mark'] ?? 'MBS' }}</span>
        <span class="text-sm font-black">{{ $assistant['launcher_label'] ?? 'Ask ModelMind' }}</span>
    </button>
</div>
