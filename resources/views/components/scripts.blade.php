<script>
    window.modelMind = ({
        endpoint,
        sessionEndpoint,
        feedbackEndpoint,
        csrfToken,
        initialMessage = null,
        quickQuestions = [],
        fallbackAnswer = null,
        storageKey = 'model-mind-state',
        browserMessages = 60,
        historyMessages = 12,
        feedbackEnabled = true,
    }) => ({
        open: false,
        draft: '',
        sending: false,
        failure: '',
        sessionId: null,
        nextLocalId: 1,
        storageKey,
        feedbackEnabled: Boolean(feedbackEnabled),
        browserMessageLimit: Math.max(30, Number(browserMessages) || 60),
        historyMessageLimit: Math.max(8, Number(historyMessages) || 12),
        quickQuestions: quickQuestions.length ? quickQuestions : [
            'What can you help with?',
            'What data can you see?',
            'How do I configure you?',
        ],
        messages: [],
        init() {
            this.messages = this.defaultMessages();
            this.restore();
            this.restoreServerHistory();
        },
        defaultMessages() {
            return [{
                localId: `local-${this.nextLocalId++}`,
                role: 'assistant',
                content: initialMessage || 'Hi, I am ModelMind. I can answer from the application data that has been safely enabled for me.',
            }];
        },
        restore() {
            try {
                const saved = JSON.parse(localStorage.getItem(this.storageKey) || '{}');

                if (saved.sessionId) {
                    this.sessionId = saved.sessionId;
                }

                if (Array.isArray(saved.messages) && saved.messages.length > 0) {
                    this.messages = saved.messages
                        .filter((message) => ['user', 'assistant'].includes(message.role) && message.content && !message.pendingAssistant)
                        .slice(-this.browserMessageLimit)
                        .map((message) => ({
                            localId: message.localId || `local-${this.nextLocalId++}`,
                            id: message.id || null,
                            role: message.role,
                            content: message.content,
                            actions: Array.isArray(message.actions) ? message.actions : [],
                            feedback: message.feedback || null,
                        }));
                }
            } catch (error) {
                localStorage.removeItem(this.storageKey);
            }
        },
        persist() {
            try {
                localStorage.setItem(this.storageKey, JSON.stringify({
                    sessionId: this.sessionId,
                    messages: this.messages
                        .filter((message) => !message.pendingAssistant)
                        .slice(-this.browserMessageLimit)
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
        },
        async restoreServerHistory() {
            if (!sessionEndpoint) {
                return;
            }

            try {
                const url = new URL(sessionEndpoint, window.location.href);

                if (this.sessionId) {
                    url.searchParams.set('session_id', this.sessionId);
                }

                const response = await fetch(url.href, {
                    headers: { 'Accept': 'application/json' },
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    return;
                }

                if (payload.session_id) {
                    this.sessionId = payload.session_id;
                }

                const serverMessages = this.normalizedServerMessages(payload.messages || []);

                if (serverMessages.length > 0 && this.shouldUseServerMessages(serverMessages)) {
                    this.messages = serverMessages;
                }

                this.persist();
                this.scrollToLatest();
            } catch (error) {
                // Local state is enough if restore fails.
            }
        },
        normalizedServerMessages(messages) {
            if (!Array.isArray(messages)) {
                return [];
            }

            return messages
                .filter((message) => ['user', 'assistant'].includes(message.role) && message.content)
                .slice(-this.browserMessageLimit)
                .map((message) => ({
                    localId: `local-${this.nextLocalId++}`,
                    id: message.id || null,
                    role: message.role,
                    content: message.content,
                    actions: Array.isArray(message.actions) ? message.actions : [],
                    feedback: message.feedback || null,
                }));
        },
        shouldUseServerMessages(serverMessages) {
            const localConversation = this.messages.filter((message) => (
                ['user', 'assistant'].includes(message.role) &&
                !message.pendingAssistant &&
                message.content &&
                !this.isInitialAssistantMessage(message)
            ));

            return localConversation.length === 0 || serverMessages.length >= localConversation.length;
        },
        isInitialAssistantMessage(message) {
            return message.role === 'assistant' &&
                !message.id &&
                message.content === (initialMessage || 'Hi, I am ModelMind. I can answer from the application data that has been safely enabled for me.');
        },
        recentHistory(exceptLocalId = null) {
            return this.messages
                .filter((message) => ['user', 'assistant'].includes(message.role) && !message.pendingAssistant && message.localId !== exceptLocalId)
                .slice(-this.historyMessageLimit)
                .map((message) => ({
                    role: message.role,
                    content: message.content,
                }));
        },
        toggle() {
            this.open = !this.open;

            if (this.open) {
                this.scrollToLatest();
            }
        },
        async ask(question = null) {
            const text = (question || this.draft || '').trim();

            if (text.length < 2 || this.sending) {
                return;
            }

            this.failure = '';
            this.draft = '';
            const userMessage = {
                localId: `local-${this.nextLocalId++}`,
                role: 'user',
                content: text,
                actions: [],
                feedback: null,
            };
            this.messages.push(userMessage);
            const history = this.recentHistory(userMessage.localId);
            const pendingMessage = {
                localId: `local-${this.nextLocalId++}`,
                role: 'assistant',
                content: '',
                pendingAssistant: true,
            };
            this.messages.push(pendingMessage);
            this.sending = true;
            this.persist();
            this.scrollToLatest();

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        question: text,
                        history,
                    }),
                });
                const payload = await response.json().catch(() => ({}));

                if (response.status === 422) {
                    this.failure = Object.values(payload.errors || {})?.[0]?.[0] || 'Please ask a shorter question.';
                    return;
                }

                if (!response.ok) {
                    throw new Error(payload.message || 'ModelMind is unavailable right now.');
                }

                this.sessionId = payload.session_id || this.sessionId;
                userMessage.id = payload.user_message_id || userMessage.id || null;
                this.messages = this.messages.filter((message) => message.localId !== pendingMessage.localId);
                this.messages.push({
                    localId: `local-${this.nextLocalId++}`,
                    id: payload.message_id || null,
                    role: 'assistant',
                    content: payload.answer || fallbackAnswer || 'I do not have that information in the enabled application context yet.',
                    actions: Array.isArray(payload.actions) ? payload.actions : [],
                    feedback: null,
                });
            } catch (error) {
                this.failure = error.message || 'ModelMind is unavailable right now.';
            } finally {
                this.messages = this.messages.filter((message) => message.localId !== pendingMessage.localId);
                this.sending = false;
                this.persist();
                this.scrollToLatest();
            }
        },
        async sendFeedback(message, feedback) {
            if (!this.feedbackEnabled || !message?.id || message.feedbackSending) {
                return;
            }

            message.feedbackSending = true;

            try {
                const response = await fetch(`${feedbackEndpoint}/${message.id}/feedback`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        feedback,
                    }),
                });
                const payload = await response.json().catch(() => ({}));

                if (response.ok) {
                    message.feedback = payload.feedback || feedback;
                    this.persist();
                }
            } finally {
                message.feedbackSending = false;
            }
        },
        handleAction(action) {
            if (!action?.url) {
                return;
            }

            const url = new URL(action.url, window.location.href);

            if (url.origin === window.location.origin) {
                window.location.href = url.href;
                return;
            }

            window.open(url.href, '_blank', 'noopener,noreferrer');
        },
        scrollToLatest() {
            this.$nextTick(() => {
                if (this.$refs.messages) {
                    this.$refs.messages.scrollTo({
                        top: this.$refs.messages.scrollHeight,
                        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                    });
                }
            });
        },
    });
</script>
