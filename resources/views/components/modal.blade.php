@php
    $assistant = config('model-mind.assistant');
    $modelMindConfig = [
        'endpoint' => route(config('model-mind.routes.name', 'model-mind.').'chat'),
        'sessionEndpoint' => route(config('model-mind.routes.name', 'model-mind.').'session'),
        'feedbackEndpoint' => url(config('model-mind.routes.prefix', 'model-mind').'/messages'),
        'csrfToken' => csrf_token(),
        'initialMessage' => $assistant['initial_message'] ?? null,
        'quickQuestions' => $assistant['quick_questions'] ?? [],
        'fallbackAnswer' => $assistant['fallback_answer'] ?? null,
        'storageKey' => config('model-mind.ui.storage_key', 'model-mind-state'),
        'browserMessages' => (int) config('model-mind.memory.browser_messages', 60),
        'historyMessages' => (int) config('model-mind.memory.recent_messages', 12),
        'feedbackEnabled' => (bool) config('model-mind.features.feedback', true),
    ];
@endphp

<div
    class="fixed inset-x-3 bottom-5 z-[9999] print:hidden sm:inset-x-auto sm:right-5 sm:w-[25rem]"
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
