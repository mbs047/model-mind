<script>
    (() => {
        const defaultInitialMessage = 'Hi, I am ModelMind. I can answer from the application data that has been safely enabled for me.';
        const defaultFallbackAnswer = 'I do not have that information in the enabled application context yet.';
        const defaultQuickQuestions = [
            'What can you help with?',
            'What data can you see?',
            'How do I configure you?',
        ];

        const createElement = (tag, options = {}) => {
            const element = document.createElement(tag);

            if (options.className) {
                element.className = options.className;
            }

            if (options.text !== undefined) {
                element.textContent = options.text;
            }

            Object.entries(options.attributes || {}).forEach(([name, value]) => {
                element.setAttribute(name, value);
            });

            (options.children || []).forEach((child) => {
                element.append(child);
            });

            return element;
        };

        const readConfig = (widget) => {
            try {
                return JSON.parse(widget.querySelector('[data-model-mind-config]')?.textContent || '{}');
            } catch (error) {
                return {};
            }
        };

        const initWidget = (widget) => {
            if (widget.dataset.modelMindReady === 'true') {
                return;
            }

            widget.dataset.modelMindReady = 'true';

            const config = readConfig(widget);
            const panel = widget.querySelector('[data-model-mind-panel]');
            const messagesContainer = widget.querySelector('[data-model-mind-messages]');
            const quickQuestionsContainer = widget.querySelector('[data-model-mind-quick-questions]');
            const form = widget.querySelector('[data-model-mind-form]');
            const draft = widget.querySelector('[data-model-mind-draft]');
            const submit = widget.querySelector('[data-model-mind-submit]');
            const failure = widget.querySelector('[data-model-mind-failure]');
            const toggle = widget.querySelector('[data-model-mind-toggle]');
            const close = widget.querySelector('[data-model-mind-close]');

            if (!panel || !messagesContainer || !form || !draft || !submit || !toggle) {
                return;
            }

            const state = {
                open: false,
                sending: false,
                failure: '',
                sessionId: null,
                nextLocalId: 1,
                storageKey: config.storageKey || 'model-mind-state',
                feedbackEnabled: Boolean(config.feedbackEnabled ?? true),
                browserMessageLimit: Math.max(30, Number(config.browserMessages) || 60),
                historyMessageLimit: Math.max(8, Number(config.historyMessages) || 12),
                quickQuestions: Array.isArray(config.quickQuestions) && config.quickQuestions.length
                    ? config.quickQuestions
                    : defaultQuickQuestions,
                messages: [],
            };

            const initialMessage = config.initialMessage || defaultInitialMessage;
            const fallbackAnswer = config.fallbackAnswer || defaultFallbackAnswer;

            const defaultMessages = () => [{
                localId: `local-${state.nextLocalId++}`,
                role: 'assistant',
                content: initialMessage,
            }];

            const scrollToLatest = () => {
                requestAnimationFrame(() => {
                    messagesContainer.scrollTo({
                        top: messagesContainer.scrollHeight,
                        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                    });
                });
            };

            const persist = () => {
                try {
                    localStorage.setItem(state.storageKey, JSON.stringify({
                        sessionId: state.sessionId,
                        messages: state.messages
                            .filter((message) => !message.pendingAssistant)
                            .slice(-state.browserMessageLimit)
                            .map((message) => ({
                                localId: message.localId,
                                id: message.id || null,
                                role: message.role,
                                content: message.content,
                                actions: Array.isArray(message.actions) ? message.actions : [],
                                feedback: message.feedback || null,
                            })),
                    }));
                } catch (error) {
                    // Local storage may be disabled.
                }
            };

            const restore = () => {
                try {
                    const saved = JSON.parse(localStorage.getItem(state.storageKey) || '{}');

                    if (saved.sessionId) {
                        state.sessionId = saved.sessionId;
                    }

                    if (Array.isArray(saved.messages) && saved.messages.length > 0) {
                        state.messages = saved.messages
                            .filter((message) => ['user', 'assistant'].includes(message.role) && message.content && !message.pendingAssistant)
                            .slice(-state.browserMessageLimit)
                            .map((message) => ({
                                localId: message.localId || `local-${state.nextLocalId++}`,
                                id: message.id || null,
                                role: message.role,
                                content: message.content,
                                actions: Array.isArray(message.actions) ? message.actions : [],
                                feedback: message.feedback || null,
                            }));
                    }
                } catch (error) {
                    localStorage.removeItem(state.storageKey);
                }
            };

            const isInitialAssistantMessage = (message) => (
                message.role === 'assistant' &&
                !message.id &&
                message.content === initialMessage
            );

            const normalizedServerMessages = (messages) => {
                if (!Array.isArray(messages)) {
                    return [];
                }

                return messages
                    .filter((message) => ['user', 'assistant'].includes(message.role) && message.content)
                    .slice(-state.browserMessageLimit)
                    .map((message) => ({
                        localId: `local-${state.nextLocalId++}`,
                        id: message.id || null,
                        role: message.role,
                        content: message.content,
                        actions: Array.isArray(message.actions) ? message.actions : [],
                        feedback: message.feedback || null,
                    }));
            };

            const shouldUseServerMessages = (serverMessages) => {
                const localConversation = state.messages.filter((message) => (
                    ['user', 'assistant'].includes(message.role) &&
                    !message.pendingAssistant &&
                    message.content &&
                    !isInitialAssistantMessage(message)
                ));

                return localConversation.length === 0 || serverMessages.length >= localConversation.length;
            };

            const recentHistory = (exceptLocalId = null) => state.messages
                .filter((message) => ['user', 'assistant'].includes(message.role) && !message.pendingAssistant && message.localId !== exceptLocalId)
                .slice(-state.historyMessageLimit)
                .map((message) => ({
                    role: message.role,
                    content: message.content,
                }));

            const sendFeedback = async (message, feedbackValue) => {
                if (!state.feedbackEnabled || !message?.id || message.feedbackSending) {
                    return;
                }

                message.feedbackSending = true;
                render();

                try {
                    const response = await fetch(`${config.feedbackEndpoint}/${message.id}/feedback`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                        body: JSON.stringify({
                            session_id: state.sessionId,
                            feedback: feedbackValue,
                        }),
                    });
                    const payload = await response.json().catch(() => ({}));

                    if (response.ok) {
                        message.feedback = payload.feedback || feedbackValue;
                        persist();
                    }
                } finally {
                    message.feedbackSending = false;
                    render();
                }
            };

            const handleAction = (action) => {
                if (!action?.url) {
                    return;
                }

                const url = new URL(action.url, window.location.href);

                if (url.origin === window.location.origin) {
                    window.location.href = url.href;
                    return;
                }

                window.open(url.href, '_blank', 'noopener,noreferrer');
            };

            const renderActions = (message) => {
                if (message.role !== 'assistant' || message.pendingAssistant || !Array.isArray(message.actions) || message.actions.length === 0) {
                    return null;
                }

                return createElement('div', {
                    className: 'mt-2 flex flex-wrap gap-2',
                    children: message.actions.map((action) => {
                        const actionButton = createElement('button', {
                            className: 'inline-flex max-w-full items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm transition hover:border-slate-400 hover:text-slate-950 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-white/25',
                            attributes: { type: 'button' },
                            children: [
                                createElement('span', { className: 'truncate', text: action.label || action.url }),
                                createElement('span', { text: '\u2197', attributes: { 'aria-hidden': 'true' } }),
                            ],
                        });

                        actionButton.addEventListener('click', () => handleAction(action));

                        return actionButton;
                    }),
                });
            };

            const renderFeedback = (message) => {
                if (!state.feedbackEnabled || message.role !== 'assistant' || !message.id || message.pendingAssistant) {
                    return null;
                }

                const wrapper = createElement('div', { className: 'mt-2 flex gap-1' });
                const buttons = [
                    ['liked', 'Helpful', 'Mark helpful'],
                    ['disliked', 'Not helpful', 'Mark not helpful'],
                ];

                buttons.forEach(([value, label, ariaLabel]) => {
                    const activeClass = value === 'liked'
                        ? ' border-emerald-400 text-emerald-700 dark:text-emerald-300'
                        : ' border-rose-400 text-rose-700 dark:text-rose-300';
                    const button = createElement('button', {
                        className: `rounded-full border border-slate-200 px-2 py-1 text-xs font-bold text-slate-500 transition hover:text-slate-950 disabled:opacity-50 dark:border-white/10 dark:text-slate-400 dark:hover:text-white${message.feedback === value ? activeClass : ''}`,
                        text: label,
                        attributes: {
                            type: 'button',
                            'aria-label': ariaLabel,
                        },
                    });

                    button.disabled = Boolean(message.feedbackSending);
                    button.addEventListener('click', () => sendFeedback(message, value));
                    wrapper.append(button);
                });

                return wrapper;
            };

            const renderMessage = (message) => {
                const bubbleClasses = message.role === 'user'
                    ? 'rounded-br-md bg-slate-950 font-semibold text-white dark:bg-white dark:text-slate-950'
                    : 'rounded-bl-md border border-slate-200 bg-white text-slate-800 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100';

                const bubbleContent = message.pendingAssistant
                    ? createElement('span', {
                        className: 'inline-flex items-center gap-1 font-semibold text-slate-500',
                        children: [
                            createElement('span', { text: 'Writing' }),
                            createElement('span', {
                                className: 'mb-1 inline-flex gap-0.5',
                                attributes: { 'aria-hidden': 'true' },
                                children: [
                                    createElement('span', { className: 'model-mind-thinking-dot', text: '.' }),
                                    createElement('span', { className: 'model-mind-thinking-dot', text: '.' }),
                                    createElement('span', { className: 'model-mind-thinking-dot', text: '.' }),
                                ],
                            }),
                        ],
                    })
                    : document.createTextNode(message.content);

                const stack = createElement('div', {
                    className: 'max-w-[86%]',
                    children: [
                        createElement('div', {
                            className: `rounded-2xl px-3.5 py-2.5 text-sm leading-6 shadow-sm ${bubbleClasses}`,
                            children: [bubbleContent],
                        }),
                    ],
                });
                const actions = renderActions(message);
                const feedback = renderFeedback(message);

                if (actions) {
                    stack.append(actions);
                }

                if (feedback) {
                    stack.append(feedback);
                }

                return createElement('div', {
                    className: `flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`,
                    children: [stack],
                });
            };

            const renderQuickQuestions = () => {
                if (!quickQuestionsContainer) {
                    return;
                }

                quickQuestionsContainer.replaceChildren();
                quickQuestionsContainer.hidden = state.messages.length !== 1;

                if (quickQuestionsContainer.hidden) {
                    return;
                }

                state.quickQuestions.forEach((question) => {
                    const button = createElement('button', {
                        className: 'rounded-full border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-slate-400 hover:text-slate-950 dark:border-white/10 dark:text-slate-300 dark:hover:border-white/25 dark:hover:text-white',
                        text: question,
                        attributes: { type: 'button' },
                    });

                    button.addEventListener('click', () => ask(question));
                    quickQuestionsContainer.append(button);
                });
            };

            const render = () => {
                panel.hidden = !state.open;
                toggle.setAttribute('aria-expanded', state.open ? 'true' : 'false');
                submit.disabled = state.sending || draft.value.trim().length < 2;

                if (failure) {
                    failure.textContent = state.failure;
                    failure.hidden = !state.failure;
                }

                messagesContainer.replaceChildren(...state.messages.map((message) => renderMessage(message)));
                renderQuickQuestions();
            };

            const setOpen = (open) => {
                state.open = open;
                render();

                if (state.open) {
                    scrollToLatest();
                }
            };

            const ask = async (question = null) => {
                const text = (question || draft.value || '').trim();

                if (text.length < 2 || state.sending) {
                    return;
                }

                state.failure = '';
                draft.value = '';

                const userMessage = {
                    localId: `local-${state.nextLocalId++}`,
                    role: 'user',
                    content: text,
                    actions: [],
                    feedback: null,
                };
                state.messages.push(userMessage);

                const pendingMessage = {
                    localId: `local-${state.nextLocalId++}`,
                    role: 'assistant',
                    content: '',
                    pendingAssistant: true,
                };
                state.messages.push(pendingMessage);
                state.sending = true;
                persist();
                render();
                scrollToLatest();

                try {
                    const response = await fetch(config.endpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                        body: JSON.stringify({
                            session_id: state.sessionId,
                            question: text,
                            history: recentHistory(userMessage.localId),
                        }),
                    });
                    const payload = await response.json().catch(() => ({}));

                    if (response.status === 422) {
                        state.failure = Object.values(payload.errors || {})?.[0]?.[0] || 'Please ask a shorter question.';
                        return;
                    }

                    if (!response.ok) {
                        throw new Error(payload.message || 'ModelMind is unavailable right now.');
                    }

                    state.sessionId = payload.session_id || state.sessionId;
                    userMessage.id = payload.user_message_id || userMessage.id || null;
                    state.messages = state.messages.filter((message) => message.localId !== pendingMessage.localId);
                    state.messages.push({
                        localId: `local-${state.nextLocalId++}`,
                        id: payload.message_id || null,
                        role: 'assistant',
                        content: payload.answer || fallbackAnswer,
                        actions: Array.isArray(payload.actions) ? payload.actions : [],
                        feedback: null,
                    });
                } catch (error) {
                    state.failure = error.message || 'ModelMind is unavailable right now.';
                } finally {
                    state.messages = state.messages.filter((message) => message.localId !== pendingMessage.localId);
                    state.sending = false;
                    persist();
                    render();
                    scrollToLatest();
                }
            };

            const restoreServerHistory = async () => {
                if (!config.sessionEndpoint) {
                    return;
                }

                try {
                    const url = new URL(config.sessionEndpoint, window.location.href);

                    if (state.sessionId) {
                        url.searchParams.set('session_id', state.sessionId);
                    }

                    const response = await fetch(url.href, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        return;
                    }

                    if (payload.session_id) {
                        state.sessionId = payload.session_id;
                    }

                    const serverMessages = normalizedServerMessages(payload.messages || []);

                    if (serverMessages.length > 0 && shouldUseServerMessages(serverMessages)) {
                        state.messages = serverMessages;
                    }

                    persist();
                    render();
                    scrollToLatest();
                } catch (error) {
                    // Local state is enough if restore fails.
                }
            };

            toggle.addEventListener('click', () => setOpen(!state.open));
            close?.addEventListener('click', () => setOpen(false));
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                ask();
            });
            draft.addEventListener('input', render);
            draft.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    ask();
                }
            });
            window.addEventListener('model-mind:toggle', () => setOpen(!state.open));

            state.messages = defaultMessages();
            restore();
            render();
            restoreServerHistory();
        };

        const initAll = () => {
            document.querySelectorAll('[data-model-mind-widget]').forEach(initWidget);
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAll, { once: true });
        } else {
            initAll();
        }

        window.ModelMind = {
            init: initAll,
        };
    })();
</script>
