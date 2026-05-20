@php($assistant = config('mbs-ai-chat.assistant'))

<div
    x-data="mbsAiChat({
        endpoint: '{{ route(config('mbs-ai-chat.routes.name', 'mbs-ai-chat.').'chat') }}',
        sessionEndpoint: '{{ route(config('mbs-ai-chat.routes.name', 'mbs-ai-chat.').'session') }}',
        feedbackEndpoint: '{{ url(config('mbs-ai-chat.routes.prefix', 'mbs-ai-chat').'/messages') }}',
        csrfToken: '{{ csrf_token() }}',
        initialMessage: @js($assistant['initial_message'] ?? null),
        quickQuestions: @js($assistant['quick_questions'] ?? []),
        fallbackAnswer: @js($assistant['fallback_answer'] ?? null),
        storageKey: @js(config('mbs-ai-chat.ui.storage_key', 'mbs-ai-chat-state')),
        browserMessages: @js((int) config('mbs-ai-chat.memory.browser_messages', 60)),
        historyMessages: @js((int) config('mbs-ai-chat.memory.recent_messages', 12)),
        feedbackEnabled: @js((bool) config('mbs-ai-chat.features.feedback', true)),
    })"
    x-init="init()"
    @mbs-ai-chat:toggle.window="toggle()"
    class="fixed inset-x-3 bottom-5 z-[80] print:hidden sm:inset-x-auto sm:right-5 sm:w-[25rem]"
    x-cloak
>
    <div
        x-show="open"
        id="mbs-ai-chat-panel"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="translate-y-2 scale-95 opacity-0"
        x-transition:enter-end="translate-y-0 scale-100 opacity-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="translate-y-0 scale-100 opacity-100"
        x-transition:leave-end="translate-y-2 scale-95 opacity-0"
        class="mb-3 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15 dark:border-white/10 dark:bg-slate-950"
        role="dialog"
        aria-modal="false"
        aria-labelledby="mbs-ai-chat-title"
    >
        <div class="border-b border-slate-200 bg-slate-950 p-4 text-white dark:border-white/10">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex size-10 items-center justify-center rounded-xl bg-white text-sm font-black text-slate-950">MBS</span>
                    <div>
                        <p id="mbs-ai-chat-title" class="text-sm font-bold">{{ $assistant['name'] ?? 'MBS Assistant' }}</p>
                        <p class="text-xs font-medium text-slate-300">{{ $assistant['subtitle'] ?? 'AI assistant powered by your application data' }}</p>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-full border border-white/10 text-white/75 transition hover:bg-white/10 hover:text-white"
                    @click="open = false"
                    aria-label="Close MBS Assistant"
                >
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        </div>

        <div x-ref="messages" class="max-h-[24rem] space-y-3 overflow-y-auto bg-slate-50 p-4 dark:bg-slate-900">
            <template x-for="message in messages" :key="message.id || message.localId">
                <div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[86%]">
                        <div
                            class="rounded-2xl px-3.5 py-2.5 text-sm leading-6 shadow-sm"
                            :class="message.role === 'user'
                                ? 'rounded-br-md bg-slate-950 font-semibold text-white dark:bg-white dark:text-slate-950'
                                : 'rounded-bl-md border border-slate-200 bg-white text-slate-800 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100'"
                        >
                            <span x-show="! message.pendingAssistant" x-text="message.content"></span>
                            <span x-show="message.pendingAssistant" class="inline-flex items-center gap-1 font-semibold text-slate-500">
                                <span>Writing</span>
                                <span class="mb-1 inline-flex gap-0.5" aria-hidden="true">
                                    <span class="mbs-ai-chat-thinking-dot">.</span>
                                    <span class="mbs-ai-chat-thinking-dot">.</span>
                                    <span class="mbs-ai-chat-thinking-dot">.</span>
                                </span>
                            </span>
                        </div>

                        <div x-show="message.role === 'assistant' && ! message.pendingAssistant && (message.actions || []).length" class="mt-2 flex flex-wrap gap-2">
                            <template x-for="action in message.actions || []" :key="action.url">
                                <button
                                    type="button"
                                    @click="handleAction(action)"
                                    class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm transition hover:border-slate-400 hover:text-slate-950 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-white/25"
                                >
                                    <span class="truncate" x-text="action.label"></span>
                                    <span aria-hidden="true">&nearr;</span>
                                </button>
                            </template>
                        </div>

                        <div x-show="feedbackEnabled && message.role === 'assistant' && message.id && ! message.pendingAssistant" class="mt-2 flex gap-1">
                            <button
                                type="button"
                                class="rounded-full border border-slate-200 px-2 py-1 text-xs font-bold text-slate-500 transition hover:text-slate-950 disabled:opacity-50 dark:border-white/10 dark:text-slate-400 dark:hover:text-white"
                                :class="message.feedback === 'liked' ? 'border-emerald-400 text-emerald-700 dark:text-emerald-300' : ''"
                                :disabled="message.feedbackSending"
                                @click="sendFeedback(message, 'liked')"
                                aria-label="Mark helpful"
                            >Helpful</button>
                            <button
                                type="button"
                                class="rounded-full border border-slate-200 px-2 py-1 text-xs font-bold text-slate-500 transition hover:text-slate-950 disabled:opacity-50 dark:border-white/10 dark:text-slate-400 dark:hover:text-white"
                                :class="message.feedback === 'disliked' ? 'border-rose-400 text-rose-700 dark:text-rose-300' : ''"
                                :disabled="message.feedbackSending"
                                @click="sendFeedback(message, 'disliked')"
                                aria-label="Mark not helpful"
                            >Not helpful</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="border-t border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-950">
            <div x-show="messages.length === 1" class="mb-3 flex flex-wrap gap-2">
                <template x-for="question in quickQuestions" :key="question">
                    <button
                        type="button"
                        class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-slate-400 hover:text-slate-950 dark:border-white/10 dark:text-slate-300 dark:hover:border-white/25 dark:hover:text-white"
                        x-text="question"
                        @click="ask(question)"
                    ></button>
                </template>
            </div>

            <form class="flex items-end gap-2" @submit.prevent="ask()">
                <label for="mbs-ai-chat-question" class="sr-only">Ask MBS Assistant</label>
                <textarea
                    id="mbs-ai-chat-question"
                    x-model.trim="draft"
                    rows="1"
                    maxlength="2000"
                    class="min-h-11 flex-1 resize-none rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-slate-950 focus:ring-4 focus:ring-slate-950/10 disabled:opacity-60 dark:border-white/10 dark:bg-slate-900 dark:text-white dark:focus:border-white"
                    placeholder="{{ $assistant['placeholder'] ?? 'Ask about the enabled application data' }}"
                    :disabled="sending"
                    @keydown.enter.prevent="if (!$event.shiftKey) ask()"
                ></textarea>

                <button
                    type="submit"
                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-sm font-black text-white shadow-lg shadow-slate-950/15 transition hover:scale-105 disabled:pointer-events-none disabled:opacity-50 dark:bg-white dark:text-slate-950"
                    :disabled="sending || draft.length < 2"
                    aria-label="Send question"
                >&#8593;</button>
            </form>

            <p x-show="failure" x-text="failure" class="mt-3 text-sm font-bold text-rose-600" role="alert"></p>
        </div>
    </div>

    <button
        type="button"
        class="flex items-center gap-3 rounded-full border border-slate-200 bg-white px-4 py-3 text-slate-950 shadow-xl shadow-slate-900/10 transition hover:-translate-y-0.5 hover:border-slate-400 dark:border-white/10 dark:bg-slate-950 dark:text-white"
        @click="toggle()"
        :aria-expanded="open ? 'true' : 'false'"
        aria-controls="mbs-ai-chat-panel"
    >
        <span class="inline-flex size-9 items-center justify-center rounded-full bg-slate-950 text-[0.65rem] font-black text-white dark:bg-white dark:text-slate-950">MBS</span>
        <span class="text-sm font-black">{{ $assistant['launcher_label'] ?? 'Ask MBS' }}</span>
    </button>
</div>
