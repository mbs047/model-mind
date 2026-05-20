<?php

namespace Mbs\ModelMind\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Mbs\ModelMind\Contracts\ModelMindProvider;
use Mbs\ModelMind\Contracts\StreamingModelMindProvider;
use Mbs\ModelMind\Data\ModelMindRequestData;
use Mbs\ModelMind\Data\ModelMindResponseData;
use Mbs\ModelMind\Events\AnswerGenerated;
use Mbs\ModelMind\Events\FeedbackSubmitted;
use Mbs\ModelMind\Events\MessageSent;
use Mbs\ModelMind\Http\Requests\AskModelMindRequest;
use Mbs\ModelMind\Http\Requests\FeedbackModelMindMessageRequest;
use Mbs\ModelMind\Http\Requests\TrackModelMindActionClickRequest;
use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Models\ModelMindSession;
use Mbs\ModelMind\Support\Actions\ActionExtractor;
use Mbs\ModelMind\Support\Background\ModelMindBackgroundTasks;
use Mbs\ModelMind\Support\Citations\SourceCitationExtractor;
use Mbs\ModelMind\Support\PageContext\PageContextConfig;
use Mbs\ModelMind\Support\PageContext\PageContextSanitizer;
use Mbs\ModelMind\Support\PromptBuilder;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModelMindController extends Controller
{
    public function manifest(PageContextConfig $pageContext): JsonResponse
    {
        $assistant = config('model-mind.assistant', []);
        $assistant = is_array($assistant) ? $assistant : [];

        return response()->json([
            'name' => $this->cleanManifestText($assistant['name'] ?? 'ModelMind'),
            'brand_mark' => $this->cleanManifestText($assistant['brand_mark'] ?? 'MBS'),
            'subtitle' => $this->cleanManifestText($assistant['subtitle'] ?? 'AI assistant powered by your application data'),
            'launcher_label' => $this->cleanManifestText($assistant['launcher_label'] ?? 'Ask ModelMind'),
            'placeholder' => $this->cleanManifestText($assistant['placeholder'] ?? 'Ask about the enabled application data'),
            'initial_message' => $this->cleanManifestText($assistant['initial_message'] ?? 'Hi, I am ModelMind. I can answer from the application data that has been safely enabled for me.', 600),
            'fallback_answer' => $this->cleanManifestText($assistant['fallback_answer'] ?? 'I do not have that information in the enabled application context yet.', 600),
            'default_questions' => $this->manifestQuestions($assistant['default_questions'] ?? $assistant['quick_questions'] ?? []),
            'features' => [
                'feedback' => (bool) config('model-mind.features.feedback', true),
                'actions' => (bool) config('model-mind.features.actions', true),
                'citations' => (bool) config('model-mind.features.citations', true),
                'streaming' => (bool) config('model-mind.features.streaming', false),
                'analytics' => (bool) config('model-mind.analytics.enabled', true),
                'page_context' => $pageContext->enabled(),
            ],
            'endpoints' => [
                'chat' => route((string) config('model-mind.api.name', 'model-mind.api.').'chat'),
                'stream' => route((string) config('model-mind.api.name', 'model-mind.api.').'stream'),
                'session' => route((string) config('model-mind.api.name', 'model-mind.api.').'session'),
                'feedback' => $this->apiFeedbackEndpointTemplate(),
                'action_click' => route((string) config('model-mind.api.name', 'model-mind.api.').'actions.click'),
            ],
            'limits' => [
                'question_characters' => 2000,
                'history_messages' => 20,
                'history_message_characters' => 10000,
                'feedback_note_characters' => 1000,
                'page_context_characters' => $pageContext->maxContentCharacters(),
            ],
            'session_lifetime_minutes' => max(0, (int) config('model-mind.memory.session_lifetime_minutes', 120)),
        ]);
    }

    public function chat(
        AskModelMindRequest $request,
        ModelMindProvider $provider,
        PromptBuilder $promptBuilder,
        ActionExtractor $actions,
        SourceCitationExtractor $citations,
        ModelMindBackgroundTasks $background,
        PageContextSanitizer $pageContextSanitizer,
    ): JsonResponse {
        [$session, $question, $userMessage, $pageContext] = $this->beginAssistantTurn($request, $pageContextSanitizer);
        $startedAt = hrtime(true);

        try {
            $response = $provider->answer(new ModelMindRequestData(
                question: $question,
                instructions: $promptBuilder->instructions($question),
                session: $session,
                pageContext: $pageContext,
            ));
        } catch (RuntimeException $exception) {
            report($exception);
            $background->recordChatFailed($session, $userMessage, $exception, $this->elapsedMilliseconds($startedAt));
            $background->compactSession($session);

            return response()->json([
                'message' => 'ModelMind is unavailable right now. Please try again soon.',
                'session_id' => $session->uuid,
            ], 503);
        }

        $payload = $this->completeAssistantTurn(
            session: $session,
            question: $question,
            response: $response,
            actions: $actions,
            citations: $citations,
            background: $background,
            latencyMs: $this->elapsedMilliseconds($startedAt),
        );

        return response()->json([
            'answer' => $payload['answer'],
            'actions' => $payload['actions'],
            'citations' => $payload['citations'],
            'session_id' => $session->uuid,
            'expires_at' => $this->sessionExpiresAt($session),
            'user_message_id' => $userMessage->uuid,
            'message_id' => $payload['message_id'],
        ]);
    }

    public function stream(
        AskModelMindRequest $request,
        ModelMindProvider $provider,
        PromptBuilder $promptBuilder,
        ActionExtractor $actions,
        SourceCitationExtractor $citations,
        ModelMindBackgroundTasks $background,
        PageContextSanitizer $pageContextSanitizer,
    ): StreamedResponse {
        [$session, $question, $userMessage, $pageContext] = $this->beginAssistantTurn($request, $pageContextSanitizer);
        $modelMindRequest = new ModelMindRequestData(
            question: $question,
            instructions: $promptBuilder->instructions($question),
            session: $session,
            pageContext: $pageContext,
        );

        return response()->stream(function () use ($provider, $modelMindRequest, $session, $userMessage, $question, $actions, $citations, $background): void {
            $this->sendStreamEvent('ready', [
                'session_id' => $session->uuid,
                'expires_at' => $this->sessionExpiresAt($session),
                'user_message_id' => $userMessage->uuid,
            ]);
            $startedAt = hrtime(true);

            try {
                $response = $this->streamProviderResponse($provider, $modelMindRequest);
            } catch (RuntimeException $exception) {
                report($exception);
                $background->recordChatFailed($session, $userMessage, $exception, $this->elapsedMilliseconds($startedAt));
                $background->compactSession($session);

                $this->sendStreamEvent('error', [
                    'message' => 'ModelMind is unavailable right now. Please try again soon.',
                    'session_id' => $session->uuid,
                ]);

                return;
            }

            $payload = $this->completeAssistantTurn(
                session: $session,
                question: $question,
                response: $response,
                actions: $actions,
                citations: $citations,
                background: $background,
                latencyMs: $this->elapsedMilliseconds($startedAt),
            );

            $this->sendStreamEvent('done', [
                'answer' => $payload['answer'],
                'actions' => $payload['actions'],
                'citations' => $payload['citations'],
                'session_id' => $session->uuid,
                'expires_at' => $this->sessionExpiresAt($session),
                'user_message_id' => $userMessage->uuid,
                'message_id' => $payload['message_id'],
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return array{0: ModelMindSession, 1: string, 2: ModelMindMessage, 3: array<string, mixed>}
     */
    private function beginAssistantTurn(AskModelMindRequest $request, PageContextSanitizer $pageContextSanitizer): array
    {
        $session = $this->resolveSessionFromUuid($request->validated('session_id'), $request);
        $this->importLegacyHistory($session, $request->validated('history', []));
        $pageContext = $pageContextSanitizer->sanitize((array) $request->validated('page_context', []));

        $question = $request->string('question')->squish()->toString();
        $userMessage = $session->messages()->create([
            'role' => ModelMindMessage::ROLE_USER,
            'content' => $question,
        ]);
        event(new MessageSent($session, $userMessage, $question, [
            'route' => $request->route()?->getName(),
            'transport' => $request->expectsJson() ? 'json' : 'web',
            'page_context' => $pageContext,
        ]));
        $session->markInteraction();

        return [$session, $question, $userMessage, $pageContext];
    }

    /**
     * @return array{answer: string, actions: array<int, array{label: string, url: string, kind: string}>, citations: array<int, array{model: string, record: string, source: string, columns: array<int, string>, action: array{label: string, url: string, kind: string}|null}>, message_id: string}
     */
    private function completeAssistantTurn(
        ModelMindSession $session,
        string $question,
        ModelMindResponseData $response,
        ActionExtractor $actions,
        SourceCitationExtractor $citations,
        ModelMindBackgroundTasks $background,
        int $latencyMs,
    ): array {
        $cited = $citations->prepare($response->answer, $question);
        $prepared = $actions->prepare($cited['answer']);
        $assistantMessage = $session->messages()->create([
            'role' => ModelMindMessage::ROLE_ASSISTANT,
            'content' => $prepared['answer'],
            'metadata' => [
                ...$response->metadata,
                'actions' => $prepared['actions'],
                'citations' => $cited['citations'],
            ],
        ]);
        event(new AnswerGenerated(
            session: $session,
            message: $assistantMessage,
            question: $question,
            answer: $prepared['answer'],
            actions: $prepared['actions'],
            citations: $cited['citations'],
            providerMetadata: $response->metadata,
            latencyMs: $latencyMs,
        ));
        $background->recordChatCompleted($session, $assistantMessage, $response->metadata, $latencyMs, [
            'actions_count' => count($prepared['actions']),
            'citations_count' => count($cited['citations']),
            'answer_characters' => mb_strlen($prepared['answer']),
        ]);
        $background->rememberAssistantAnswer($assistantMessage, $question);
        $background->compactSession($session);

        return [
            'answer' => $prepared['answer'],
            'actions' => $prepared['actions'],
            'citations' => $cited['citations'],
            'message_id' => $assistantMessage->uuid,
        ];
    }

    private function streamProviderResponse(ModelMindProvider $provider, ModelMindRequestData $request): ModelMindResponseData
    {
        if (! $provider instanceof StreamingModelMindProvider) {
            $response = $provider->answer($request);
            $visibleAnswer = $this->visibleStreamAnswer($response->answer);

            if ($visibleAnswer !== '') {
                $this->sendStreamEvent('delta', [
                    'delta' => $visibleAnswer,
                ]);
            }

            return $response;
        }

        $answer = '';
        $visibleAnswer = '';

        foreach ($provider->stream($request) as $delta) {
            if ($delta === '') {
                continue;
            }

            $answer .= $delta;
            $nextVisibleAnswer = $this->visibleStreamAnswer($answer);
            $visibleDelta = substr($nextVisibleAnswer, strlen($visibleAnswer));
            $visibleAnswer = $nextVisibleAnswer;

            if ($visibleDelta !== '') {
                $this->sendStreamEvent('delta', [
                    'delta' => $visibleDelta,
                ]);
            }
        }

        if (blank($answer)) {
            throw new RuntimeException('The ModelMind provider response did not include text.');
        }

        return new ModelMindResponseData(trim($answer), $provider->streamMetadata($request));
    }

    private function visibleStreamAnswer(string $answer): string
    {
        $tokens = collect([
            config('model-mind.actions.route_token', 'model_mind_route'),
            config('model-mind.citations.token', 'model_mind_source'),
        ])
            ->filter(fn (mixed $token): bool => is_string($token) && filled($token))
            ->values();

        if ($tokens->isEmpty()) {
            return $answer;
        }

        $pattern = '/\[\[(?:'.$tokens
            ->map(fn (string $token): string => preg_quote($token, '/'))
            ->implode('|').')\b[^\]]*\]\]/u';
        $visible = preg_replace($pattern, '', $answer) ?? $answer;
        $partialStart = strrpos($visible, '[[');

        if ($partialStart === false) {
            return $visible;
        }

        $partialToken = substr($visible, $partialStart + 2);

        $isPartialInternalToken = $tokens->contains(fn (string $token): bool => str_starts_with($token, $partialToken)
            || str_starts_with($partialToken, $token));

        return $isPartialInternalToken ? substr($visible, 0, $partialStart) : $visible;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendStreamEvent(string $event, array $payload): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($payload, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES)."\n\n";

        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }

    public function session(Request $request): JsonResponse
    {
        $requestedSessionId = $this->normalizeUuid($request->query('session_id'));
        $session = $this->resolveExistingSession($requestedSessionId, $request)
            ?? $this->resolveSessionFromRequestSession($request);

        if (! $session) {
            return response()->json([
                'session_id' => null,
                'expires_at' => null,
                'expired' => $requestedSessionId !== null,
                'messages' => [],
            ]);
        }

        $this->rememberSession($request, $session);

        $messages = $session->messages()
            ->reorder()
            ->latest('id')
            ->limit((int) config('model-mind.memory.browser_messages', 60))
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'session_id' => $session->uuid,
            'expires_at' => $this->sessionExpiresAt($session),
            'expired' => false,
            'messages' => $messages
                ->map(fn (ModelMindMessage $message): array => $this->messagePayload($message))
                ->all(),
        ]);
    }

    public function feedback(FeedbackModelMindMessageRequest $request, string $message, ModelMindBackgroundTasks $background): JsonResponse
    {
        $assistantMessage = ModelMindMessage::query()
            ->where('uuid', $message)
            ->firstOrFail();
        $sessionId = $request->validated('session_id');

        if (! $assistantMessage->isAssistant() || ($sessionId && $assistantMessage->session?->uuid !== $sessionId)) {
            abort(404);
        }

        $assistantMessage->forceFill([
            'feedback' => $request->validated('feedback'),
            'feedback_note' => $request->validated('note'),
            'feedback_at' => now(),
        ])->save();

        $assistantMessage->session?->markInteraction();

        if ($assistantMessage->feedback === ModelMindMessage::FEEDBACK_LIKED) {
            $background->rememberLikedAnswer($assistantMessage);
        }

        $background->recordFeedback($assistantMessage, $request);
        $background->compactSession($assistantMessage->session);
        event(new FeedbackSubmitted(
            message: $assistantMessage,
            feedback: (string) $assistantMessage->feedback,
            note: $assistantMessage->feedback_note,
        ));

        return response()->json([
            'feedback' => $assistantMessage->feedback,
        ]);
    }

    public function actionClick(TrackModelMindActionClickRequest $request, ModelMindBackgroundTasks $background): JsonResponse
    {
        $session = $this->resolveExistingSession($request->validated('session_id'), $request);
        $messageId = $request->validated('message_id');
        $message = is_string($messageId)
            ? ModelMindMessage::query()->where('uuid', $messageId)->first()
            : null;

        if (
            $session instanceof ModelMindSession
            && $message instanceof ModelMindMessage
            && (int) $message->model_mind_session_id !== (int) $session->getKey()
        ) {
            abort(404);
        }

        $background->recordActionClick($session, $message, $request->validated(), $request);

        return response()->json(['tracked' => true]);
    }

    private function resolveSessionFromUuid(?string $uuid, Request $request): ModelMindSession
    {
        $requestedUuid = $this->normalizeUuid($uuid);

        if ($requestedUuid !== null) {
            $session = $this->resolveExistingSession($requestedUuid, $request);

            if ($session instanceof ModelMindSession) {
                $this->rememberSession($request, $session);

                return $session;
            }

            $this->forgetSession($request);
        }

        if ($requestedUuid === null) {
            $session = $this->resolveSessionFromRequestSession($request);

            if ($session instanceof ModelMindSession) {
                $this->rememberSession($request, $session);

                return $session;
            }
        }

        $session = ModelMindSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent() ?? '')->limit(1000, '')->toString(),
            'last_interaction_at' => now(),
        ]);

        $this->rememberSession($request, $session);

        return $session;
    }

    private function resolveExistingSession(?string $uuid, ?Request $request = null): ?ModelMindSession
    {
        $uuid = $this->normalizeUuid($uuid);

        if (! $uuid) {
            return null;
        }

        $session = ModelMindSession::query()->where('uuid', $uuid)->first();

        if (! $session instanceof ModelMindSession) {
            return null;
        }

        if ($this->sessionExpired($session)) {
            if ($request instanceof Request) {
                $this->forgetSession($request);
            }

            return null;
        }

        return $session;
    }

    private function resolveSessionFromRequestSession(Request $request): ?ModelMindSession
    {
        return $this->resolveExistingSession($this->sessionUuidFromRequest($request));
    }

    private function sessionUuidFromRequest(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        return $this->normalizeUuid($request->session()->get('model_mind.session_id'));
    }

    private function rememberSession(Request $request, ModelMindSession $session): void
    {
        if ($request->hasSession()) {
            $request->session()->put('model_mind.session_id', $session->uuid);
        }
    }

    private function forgetSession(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget('model_mind.session_id');
        }
    }

    private function sessionExpired(ModelMindSession $session): bool
    {
        $minutes = max(0, (int) config('model-mind.memory.session_lifetime_minutes', 120));

        if ($minutes === 0) {
            return false;
        }

        $lastInteraction = $session->last_interaction_at ?? $session->updated_at ?? $session->created_at;

        return $lastInteraction === null || $lastInteraction->copy()->addMinutes($minutes)->isPast();
    }

    private function sessionExpiresAt(ModelMindSession $session): ?string
    {
        $minutes = max(0, (int) config('model-mind.memory.session_lifetime_minutes', 120));

        if ($minutes === 0) {
            return null;
        }

        $lastInteraction = $session->last_interaction_at ?? $session->updated_at ?? $session->created_at ?? now();

        return $lastInteraction->copy()->addMinutes($minutes)->toJSON();
    }

    private function normalizeUuid(mixed $uuid): ?string
    {
        if (! is_string($uuid) || blank($uuid)) {
            return null;
        }

        $uuid = trim($uuid);

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1
            ? $uuid
            : null;
    }

    /**
     * @return array<int, string>
     */
    private function manifestQuestions(mixed $questions): array
    {
        $questions = is_string($questions) ? explode('|', $questions) : (array) $questions;

        return collect($questions)
            ->filter(fn (mixed $question): bool => is_scalar($question))
            ->map(fn (mixed $question): string => $this->cleanManifestText($question, 90))
            ->filter()
            ->take(6)
            ->values()
            ->all();
    }

    private function apiFeedbackEndpointTemplate(): string
    {
        $prefix = trim((string) config('model-mind.api.prefix', 'api/model-mind'), '/');

        return url($prefix.'/messages/{message}/feedback');
    }

    private function cleanManifestText(mixed $value, int $limit = 160): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return str(strip_tags((string) $value))
            ->squish()
            ->limit($limit, '')
            ->toString();
    }

    /**
     * @return array{id: string, role: string, content: string, actions: array<int, array{label: string, url: string, kind: string}>, citations: array<int, array{model: string, record: string, source: string, columns: array<int, string>, action: array{label: string, url: string, kind: string}|null}>, feedback: string|null, created_at: string|null}
     */
    private function messagePayload(ModelMindMessage $message): array
    {
        return [
            'id' => $message->uuid,
            'role' => $message->role,
            'content' => $message->content,
            'actions' => $this->messageActions($message),
            'citations' => $this->messageCitations($message),
            'feedback' => $message->feedback,
            'created_at' => $message->created_at?->toJSON(),
        ];
    }

    /**
     * @return array<int, array{label: string, url: string, kind: string}>
     */
    private function messageActions(ModelMindMessage $message): array
    {
        $actions = $message->metadata['actions'] ?? [];

        if (! is_array($actions)) {
            return [];
        }

        return collect($actions)
            ->filter(fn (mixed $action): bool => is_array($action)
                && is_string($action['label'] ?? null)
                && is_string($action['url'] ?? null)
                && is_string($action['kind'] ?? null))
            ->map(fn (array $action): array => [
                'label' => $action['label'],
                'url' => $action['url'],
                'kind' => $action['kind'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{model: string, record: string, source: string, columns: array<int, string>, action: array{label: string, url: string, kind: string}|null}>
     */
    private function messageCitations(ModelMindMessage $message): array
    {
        $citations = $message->metadata['citations'] ?? [];

        if (! is_array($citations)) {
            return [];
        }

        return collect($citations)
            ->filter(fn (mixed $citation): bool => is_array($citation)
                && is_string($citation['model'] ?? null)
                && is_string($citation['record'] ?? null)
                && is_string($citation['source'] ?? null))
            ->map(fn (array $citation): array => [
                'model' => $citation['model'],
                'record' => $citation['record'],
                'source' => $citation['source'],
                'columns' => $this->citationColumns($citation),
                'action' => $this->citationAction($citation),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $citation
     * @return array<int, string>
     */
    private function citationColumns(array $citation): array
    {
        return collect((array) ($citation['columns'] ?? []))
            ->filter(fn (mixed $column): bool => is_string($column) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) === 1)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $citation
     * @return array{label: string, url: string, kind: string}|null
     */
    private function citationAction(array $citation): ?array
    {
        $action = $citation['action'] ?? null;

        if (
            ! is_array($action)
            || ! is_string($action['label'] ?? null)
            || ! is_string($action['url'] ?? null)
            || ! is_string($action['kind'] ?? null)
        ) {
            return null;
        }

        return [
            'label' => $action['label'],
            'url' => $action['url'],
            'kind' => $action['kind'],
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function importLegacyHistory(ModelMindSession $session, array $history): void
    {
        if ($history === [] || $session->messages()->exists()) {
            return;
        }

        collect($history)
            ->take(-12)
            ->each(function (array $message) use ($session): void {
                $session->messages()->create([
                    'role' => $message['role'] === ModelMindMessage::ROLE_ASSISTANT
                        ? ModelMindMessage::ROLE_ASSISTANT
                        : ModelMindMessage::ROLE_USER,
                    'content' => str($message['content'] ?? '')->squish()->limit(5000, '')->toString(),
                    'metadata' => ['source' => 'legacy_browser_history'],
                ]);
            });
    }
}
