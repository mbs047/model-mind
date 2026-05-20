<?php

namespace Mbs\ModelMind\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Mbs\ModelMind\Contracts\ModelMindProvider;
use Mbs\ModelMind\Data\ModelMindRequestData;
use Mbs\ModelMind\Http\Requests\AskModelMindRequest;
use Mbs\ModelMind\Http\Requests\FeedbackModelMindMessageRequest;
use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Models\ModelMindSession;
use Mbs\ModelMind\Support\Actions\ActionExtractor;
use Mbs\ModelMind\Support\Learning\LearningRepository;
use Mbs\ModelMind\Support\PromptBuilder;
use RuntimeException;

class ModelMindController extends Controller
{
    public function chat(
        AskModelMindRequest $request,
        ModelMindProvider $provider,
        PromptBuilder $promptBuilder,
        ActionExtractor $actions,
        LearningRepository $learning,
    ): JsonResponse {
        $session = $this->resolveSessionFromUuid($request->validated('session_id'), $request);
        $this->importLegacyHistory($session, $request->validated('history', []));

        $question = $request->string('question')->squish()->toString();
        $userMessage = $session->messages()->create([
            'role' => ModelMindMessage::ROLE_USER,
            'content' => $question,
        ]);
        $session->compactForPrompt();

        try {
            $response = $provider->answer(new ModelMindRequestData(
                question: $question,
                instructions: $promptBuilder->instructions(),
                session: $session,
            ));
        } catch (RuntimeException $exception) {
            report($exception);
            $session->compactForPrompt();

            return response()->json([
                'message' => 'ModelMind is unavailable right now. Please try again soon.',
                'session_id' => $session->uuid,
            ], 503);
        }

        $prepared = $actions->prepare($response->answer);
        $assistantMessage = $session->messages()->create([
            'role' => ModelMindMessage::ROLE_ASSISTANT,
            'content' => $prepared['answer'],
            'metadata' => [
                ...$response->metadata,
                'actions' => $prepared['actions'],
            ],
        ]);
        $learning->rememberAssistantAnswer($prepared['answer'], [
            'message_id' => $assistantMessage->uuid,
            'session_id' => $session->uuid,
            'question' => $question,
        ]);
        $session->compactForPrompt();

        return response()->json([
            'answer' => $prepared['answer'],
            'actions' => $prepared['actions'],
            'session_id' => $session->uuid,
            'user_message_id' => $userMessage->uuid,
            'message_id' => $assistantMessage->uuid,
        ]);
    }

    public function session(Request $request): JsonResponse
    {
        $session = $this->resolveExistingSession($this->normalizeUuid($request->query('session_id')))
            ?? $this->resolveSessionFromRequestSession($request);

        if (! $session) {
            return response()->json([
                'session_id' => null,
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
            'messages' => $messages
                ->map(fn (ModelMindMessage $message): array => $this->messagePayload($message))
                ->all(),
        ]);
    }

    public function feedback(FeedbackModelMindMessageRequest $request, string $message): JsonResponse
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

        $assistantMessage->session?->compactForPrompt();

        if ($assistantMessage->feedback === ModelMindMessage::FEEDBACK_LIKED) {
            app(LearningRepository::class)->rememberLikedAnswer($assistantMessage);
        }

        return response()->json([
            'feedback' => $assistantMessage->feedback,
        ]);
    }

    private function resolveSessionFromUuid(?string $uuid, Request $request): ModelMindSession
    {
        $uuid = $this->normalizeUuid($uuid)
            ?? $this->sessionUuidFromRequest($request)
            ?? (string) Str::uuid();

        $session = ModelMindSession::query()->firstOrCreate(
            ['uuid' => $uuid],
            [
                'ip_address' => $request->ip(),
                'user_agent' => str($request->userAgent() ?? '')->limit(1000, '')->toString(),
                'last_interaction_at' => now(),
            ],
        );

        $this->rememberSession($request, $session);

        return $session;
    }

    private function resolveExistingSession(?string $uuid): ?ModelMindSession
    {
        $uuid = $this->normalizeUuid($uuid);

        if (! $uuid) {
            return null;
        }

        return ModelMindSession::query()->where('uuid', $uuid)->first();
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
     * @return array{id: string, role: string, content: string, actions: array<int, array{label: string, url: string, kind: string}>, feedback: string|null, created_at: string|null}
     */
    private function messagePayload(ModelMindMessage $message): array
    {
        return [
            'id' => $message->uuid,
            'role' => $message->role,
            'content' => $message->content,
            'actions' => $this->messageActions($message),
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
