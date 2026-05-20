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
        const theme = ['auto', 'light', 'dark'].includes(config.theme) ? config.theme : 'auto';
        widget.dataset.modelMindTheme = theme;
        widget.classList.toggle('dark', theme === 'dark');

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
            streamingEnabled: Boolean(config.streamingEnabled && config.streamEndpoint && window.ReadableStream),
            browserMessageLimit: Math.max(30, Number(config.browserMessages) || 60),
            historyMessageLimit: Math.max(8, Number(config.historyMessages) || 12),
            sessionLifetimeMinutes: Math.max(0, Number(config.sessionLifetimeMinutes) || 0),
            sessionExpiresAt: null,
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
            citations: [],
        }];

        const nextSessionExpiresAt = () => (
            state.sessionLifetimeMinutes > 0
                ? new Date(Date.now() + (state.sessionLifetimeMinutes * 60 * 1000)).toISOString()
                : null
        );

        const isExpired = (expiresAt) => (
            typeof expiresAt === 'string' &&
            expiresAt.length > 0 &&
            Date.parse(expiresAt) <= Date.now()
        );

        const resetLocalSession = () => {
            state.sessionId = null;
            state.sessionExpiresAt = null;
            state.messages = defaultMessages();

            try {
                localStorage.removeItem(state.storageKey);
            } catch (error) {
                // Local storage may be disabled.
            }
        };

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
                state.sessionExpiresAt = nextSessionExpiresAt();
                localStorage.setItem(state.storageKey, JSON.stringify({
                    sessionId: state.sessionId,
                    expiresAt: state.sessionExpiresAt,
                    messages: state.messages
                        .filter((message) => !message.pendingAssistant)
                        .slice(-state.browserMessageLimit)
                        .map((message) => ({
                            localId: message.localId,
                            id: message.id || null,
                            role: message.role,
                            content: message.content,
                            actions: Array.isArray(message.actions) ? message.actions : [],
                            citations: Array.isArray(message.citations) ? message.citations : [],
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

                if (isExpired(saved.expiresAt)) {
                    resetLocalSession();

                    return;
                }

                if (saved.sessionId) {
                    state.sessionId = saved.sessionId;
                }

                if (saved.expiresAt) {
                    state.sessionExpiresAt = saved.expiresAt;
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
                            citations: Array.isArray(message.citations) ? message.citations : [],
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
                    citations: Array.isArray(message.citations) ? message.citations : [],
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

        const trackActionClick = (action, message = null, source = 'unknown', index = 0) => {
            if (!config.actionClickEndpoint || !action?.url) {
                return;
            }

            try {
                fetch(config.actionClickEndpoint, {
                    method: 'POST',
                    keepalive: true,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({
                        _token: config.csrfToken,
                        session_id: state.sessionId,
                        message_id: message?.id || null,
                        label: action.label || null,
                        url: action.url,
                        kind: action.kind || null,
                        source,
                        index,
                    }),
                }).catch(() => {});
            } catch (error) {
                // Analytics should never block navigation.
            }
        };

        const handleAction = (action, message = null, source = 'unknown', index = 0) => {
            if (!action?.url) {
                return;
            }

            trackActionClick(action, message, source, index);

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
                children: message.actions.map((action, index) => {
                    const actionButton = createElement('button', {
                        className: 'inline-flex max-w-full items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm transition hover:border-slate-400 hover:text-slate-950 dark:border-white/10 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-white/25',
                        attributes: { type: 'button' },
                        children: [
                            createElement('span', { className: 'truncate', text: action.label || action.url }),
                            createElement('span', { text: '\u2197', attributes: { 'aria-hidden': 'true' } }),
                        ],
                    });

                    actionButton.addEventListener('click', () => handleAction(action, message, 'action', index));

                    return actionButton;
                }),
            });
        };

        const renderCitations = (message) => {
            if (message.role !== 'assistant' || message.pendingAssistant || !Array.isArray(message.citations) || message.citations.length === 0) {
                return null;
            }

            return createElement('div', {
                className: 'mt-2 space-y-2 rounded-2xl border border-slate-200 bg-white/80 p-2.5 shadow-sm dark:border-white/10 dark:bg-slate-950/80',
                children: [
                    createElement('p', {
                        className: 'px-1 text-[0.68rem] font-black uppercase tracking-normal text-slate-500 dark:text-slate-400',
                        text: 'Sources',
                    }),
                    ...message.citations.map((citation, index) => {
                        const sourceAction = citation.action?.url
                            ? createElement('button', {
                                className: 'rounded-full border border-slate-200 px-2.5 py-1 text-[0.68rem] font-black text-slate-700 transition hover:border-slate-400 hover:text-slate-950 dark:border-white/10 dark:text-slate-200 dark:hover:border-white/25',
                                text: citation.action.label || 'Open',
                                attributes: { type: 'button' },
                            })
                            : null;

                        if (sourceAction) {
                            sourceAction.addEventListener('click', () => handleAction(citation.action, message, 'citation', index));
                        }

                        return createElement('div', {
                            className: 'rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 dark:border-white/10 dark:bg-slate-900',
                            children: [
                                createElement('div', {
                                    className: 'flex items-start justify-between gap-2',
                                    children: [
                                        createElement('div', {
                                            className: 'min-w-0',
                                            children: [
                                                createElement('p', {
                                                    className: 'truncate text-[0.68rem] font-bold text-slate-500 dark:text-slate-400',
                                                    text: citation.model || 'Model',
                                                }),
                                                createElement('p', {
                                                    className: 'truncate text-xs font-black text-slate-800 dark:text-slate-100',
                                                    text: citation.record || citation.source || 'Record',
                                                }),
                                            ],
                                        }),
                                        ...(sourceAction ? [sourceAction] : []),
                                    ],
                                }),
                                ...(Array.isArray(citation.columns) && citation.columns.length
                                    ? [
                                        createElement('p', {
                                            className: 'mt-1 break-words text-[0.68rem] font-semibold text-slate-500 dark:text-slate-400',
                                            text: `Fields: ${citation.columns.join(', ')}`,
                                        }),
                                    ]
                                    : []),
                            ],
                        });
                    }),
                ],
            });
        };

        const renderFeedback = (message) => {
            if (!state.feedbackEnabled || message.role !== 'assistant' || !message.id || message.pendingAssistant) {
                return null;
            }

            const wrapper = createElement('div', { className: 'mt-2 flex flex-wrap gap-2' });
            const buttons = [
                ['liked', 'Helpful', 'Mark helpful'],
                ['disliked', 'Not helpful', 'Mark not helpful'],
            ];

            buttons.forEach(([value, label, ariaLabel]) => {
                const isSelected = message.feedback === value;
                const isDisabledBySelection = Boolean(message.feedback && !isSelected);
                const selectedClass = value === 'liked'
                    ? ' border-emerald-500 bg-emerald-50 text-emerald-700 shadow-sm ring-2 ring-emerald-500/10 dark:border-emerald-400 dark:bg-emerald-500/10 dark:text-emerald-200'
                    : ' border-rose-500 bg-rose-50 text-rose-700 shadow-sm ring-2 ring-rose-500/10 dark:border-rose-400 dark:bg-rose-500/10 dark:text-rose-200';
                const unavailableClass = ' border-slate-200 bg-slate-100 text-slate-400 dark:border-white/10 dark:bg-white/5 dark:text-slate-500';
                const idleClass = ' border-slate-200 bg-white text-slate-600 hover:border-slate-400 hover:text-slate-950 dark:border-white/10 dark:bg-slate-950 dark:text-slate-300 dark:hover:border-white/25 dark:hover:text-white';
                const button = createElement('button', {
                    className: `inline-flex min-h-8 items-center justify-center rounded-full border px-3 py-1.5 text-xs font-bold transition disabled:cursor-not-allowed${isSelected ? selectedClass : (isDisabledBySelection ? unavailableClass : idleClass)}`,
                    text: label,
                    attributes: {
                        type: 'button',
                        'aria-label': ariaLabel,
                        'aria-pressed': isSelected ? 'true' : 'false',
                        title: ariaLabel,
                    },
                });

                button.disabled = Boolean(message.feedbackSending || isDisabledBySelection);

                if (!message.feedback) {
                    button.addEventListener('click', () => sendFeedback(message, value));
                }

                wrapper.append(button);
            });

            return wrapper;
        };

        const renderMessage = (message) => {
            const bubbleClasses = message.role === 'user'
                ? 'rounded-br-md bg-slate-950 font-semibold text-white dark:bg-white dark:text-slate-950'
                : 'rounded-bl-md border border-slate-200 bg-white text-slate-800 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100';

            const bubbleContent = message.pendingAssistant && !message.content
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
            const citations = renderCitations(message);
            const feedback = renderFeedback(message);

            if (actions) {
                stack.append(actions);
            }

            if (citations) {
                stack.append(citations);
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

        const requestPayload = (text, userMessage) => ({
            session_id: state.sessionId,
            question: text,
            history: recentHistory(userMessage.localId),
        });

        const applyFinalPayload = (payload, userMessage, pendingMessage) => {
            state.sessionId = payload.session_id || state.sessionId;
            state.sessionExpiresAt = payload.expires_at || nextSessionExpiresAt();
            userMessage.id = payload.user_message_id || userMessage.id || null;
            state.messages = state.messages.filter((message) => message.localId !== pendingMessage.localId);
            state.messages.push({
                localId: `local-${state.nextLocalId++}`,
                id: payload.message_id || null,
                role: 'assistant',
                content: payload.answer || pendingMessage.content || fallbackAnswer,
                actions: Array.isArray(payload.actions) ? payload.actions : [],
                citations: Array.isArray(payload.citations) ? payload.citations : [],
                feedback: null,
            });
        };

        const jsonPayload = async (response) => response.json().catch(() => ({}));

        const sendJsonMessage = async (text, userMessage, pendingMessage) => {
            const response = await fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                body: JSON.stringify(requestPayload(text, userMessage)),
            });
            const payload = await jsonPayload(response);

            if (response.status === 422) {
                state.failure = Object.values(payload.errors || {})?.[0]?.[0] || 'Please ask a shorter question.';
                return;
            }

            if (!response.ok) {
                throw new Error(payload.message || 'ModelMind is unavailable right now.');
            }

            applyFinalPayload(payload, userMessage, pendingMessage);
        };

        const readStream = async (response, handlers) => {
            const reader = response.body?.getReader();

            if (!reader) {
                throw new Error('Streaming is not supported by this browser.');
            }

            const decoder = new TextDecoder();
            let buffer = '';

            const parseEvent = (rawEvent) => {
                const lines = rawEvent.split('\n');
                let eventName = 'message';
                const data = [];

                lines.forEach((line) => {
                    if (line.startsWith('event:')) {
                        eventName = line.slice(6).trim();
                    }

                    if (line.startsWith('data:')) {
                        data.push(line.slice(5).trimStart());
                    }
                });

                if (data.length === 0) {
                    return;
                }

                let payload = {};

                try {
                    payload = JSON.parse(data.join('\n'));
                } catch (error) {
                    payload = {};
                }

                handlers[eventName]?.(payload);
            };

            const parseBuffer = (force = false) => {
                buffer = buffer.replace(/\r\n/g, '\n');
                let separator = buffer.indexOf('\n\n');

                while (separator !== -1) {
                    const rawEvent = buffer.slice(0, separator);
                    buffer = buffer.slice(separator + 2);
                    parseEvent(rawEvent);
                    separator = buffer.indexOf('\n\n');
                }

                if (force && buffer.trim()) {
                    parseEvent(buffer);
                    buffer = '';
                }
            };

            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });
                parseBuffer();
            }

            buffer += decoder.decode();
            parseBuffer(true);
        };

        const sendStreamingMessage = async (text, userMessage, pendingMessage) => {
            const response = await fetch(config.streamEndpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json, text/event-stream',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                body: JSON.stringify(requestPayload(text, userMessage)),
            });

            if (!response.ok) {
                const payload = await jsonPayload(response);

                if (response.status === 422) {
                    state.failure = Object.values(payload.errors || {})?.[0]?.[0] || 'Please ask a shorter question.';
                    return;
                }

                throw new Error(payload.message || 'ModelMind is unavailable right now.');
            }

            let completed = false;
            let streamError = null;

            await readStream(response, {
                ready: (payload) => {
                    state.sessionId = payload.session_id || state.sessionId;
                    state.sessionExpiresAt = payload.expires_at || nextSessionExpiresAt();
                    userMessage.id = payload.user_message_id || userMessage.id || null;
                },
                delta: (payload) => {
                    pendingMessage.content = `${pendingMessage.content || ''}${payload.delta || ''}`;
                    render();
                    scrollToLatest();
                },
                done: (payload) => {
                    completed = true;
                    applyFinalPayload(payload, userMessage, pendingMessage);
                },
                error: (payload) => {
                    streamError = payload.message || 'ModelMind is unavailable right now.';
                },
            });

            if (streamError) {
                throw new Error(streamError);
            }

            if (!completed) {
                throw new Error('ModelMind did not finish the streamed response.');
            }
        };

        const ask = async (question = null) => {
            if (isExpired(state.sessionExpiresAt)) {
                resetLocalSession();
            }

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
                citations: [],
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
                if (state.streamingEnabled) {
                    await sendStreamingMessage(text, userMessage, pendingMessage);
                } else {
                    await sendJsonMessage(text, userMessage, pendingMessage);
                }
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

                if (payload.expired || (state.sessionId && payload.session_id === null)) {
                    resetLocalSession();
                    render();

                    return;
                }

                if (payload.session_id) {
                    state.sessionId = payload.session_id;
                }

                state.sessionExpiresAt = payload.expires_at || state.sessionExpiresAt;

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
