<?php

namespace Mbs\ModelMind\Support\Background;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;
use Mbs\ModelMind\Jobs\CompactModelMindSession;
use Mbs\ModelMind\Jobs\RecordModelMindActionClick;
use Mbs\ModelMind\Jobs\RecordModelMindChatCompleted;
use Mbs\ModelMind\Jobs\RecordModelMindChatFailed;
use Mbs\ModelMind\Jobs\RecordModelMindFeedback;
use Mbs\ModelMind\Jobs\RememberModelMindAssistantAnswer;
use Mbs\ModelMind\Jobs\RememberModelMindLikedAnswer;
use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Models\ModelMindSession;
use Throwable;

class ModelMindBackgroundTasks
{
    public function __construct(private readonly Dispatcher $bus) {}

    public function compactSession(?ModelMindSession $session): void
    {
        if (! $session instanceof ModelMindSession) {
            return;
        }

        $this->dispatch(new CompactModelMindSession((int) $session->getKey()));
    }

    /**
     * @param  array<string, mixed>  $providerMetadata
     * @param  array<string, mixed>  $context
     */
    public function recordChatCompleted(
        ModelMindSession $session,
        ModelMindMessage $message,
        array $providerMetadata,
        int $latencyMs,
        array $context = [],
    ): void {
        $this->dispatch(new RecordModelMindChatCompleted(
            sessionId: (int) $session->getKey(),
            messageId: (int) $message->getKey(),
            providerMetadata: $providerMetadata,
            latencyMs: $latencyMs,
            context: $context,
        ));
    }

    public function recordChatFailed(ModelMindSession $session, ModelMindMessage $message, Throwable $exception, int $latencyMs): void
    {
        $this->dispatch(new RecordModelMindChatFailed(
            sessionId: (int) $session->getKey(),
            messageId: (int) $message->getKey(),
            errorClass: $exception::class,
            errorMessage: $exception->getMessage(),
            latencyMs: $latencyMs,
        ));
    }

    public function recordFeedback(ModelMindMessage $message, ?Request $request = null): void
    {
        $this->dispatch(new RecordModelMindFeedback((int) $message->getKey(), $this->requestMetadata($request)));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordActionClick(
        ?ModelMindSession $session,
        ?ModelMindMessage $message,
        array $payload,
        ?Request $request = null,
    ): void {
        $this->dispatch(new RecordModelMindActionClick(
            sessionId: $session instanceof ModelMindSession ? (int) $session->getKey() : null,
            messageId: $message instanceof ModelMindMessage ? (int) $message->getKey() : null,
            payload: $payload,
            requestMetadata: $this->requestMetadata($request),
        ));
    }

    public function rememberAssistantAnswer(ModelMindMessage $message, string $question): void
    {
        $this->dispatch(new RememberModelMindAssistantAnswer((int) $message->getKey(), $question));
    }

    public function rememberLikedAnswer(ModelMindMessage $message): void
    {
        $this->dispatch(new RememberModelMindLikedAnswer((int) $message->getKey()));
    }

    private function dispatch(object $job): void
    {
        if ($this->mode() === 'sync') {
            $this->bus->dispatchSync($job);

            return;
        }

        $connection = $this->connection();
        $queue = $this->queue();

        if ($connection !== null && method_exists($job, 'onConnection')) {
            $job->onConnection($connection);
        }

        if ($queue !== null && method_exists($job, 'onQueue')) {
            $job->onQueue($queue);
        }

        $pendingDispatch = dispatch($job);

        if ($this->afterCommit() && method_exists($pendingDispatch, 'afterCommit')) {
            $pendingDispatch->afterCommit();
        }

        if ($this->mode() === 'after_response' && method_exists($pendingDispatch, 'afterResponse')) {
            $pendingDispatch->afterResponse();
        }
    }

    private function mode(): string
    {
        $mode = config('model-mind.background.mode', 'after_response');

        if (! is_string($mode) || ! in_array($mode, ['sync', 'after_response', 'queue'], true)) {
            return 'after_response';
        }

        return $mode;
    }

    private function connection(): ?string
    {
        $connection = config('model-mind.background.connection');

        return is_string($connection) && filled($connection) ? $connection : null;
    }

    private function queue(): ?string
    {
        $queue = config('model-mind.background.queue', 'model-mind');

        return is_string($queue) && filled($queue) ? $queue : null;
    }

    private function afterCommit(): bool
    {
        return (bool) config('model-mind.background.after_commit', true);
    }

    /**
     * @return array{ip_address?: string|null, user_agent?: string|null}
     */
    private function requestMetadata(?Request $request): array
    {
        if (! $request instanceof Request) {
            return [];
        }

        return [
            'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent() ?? '')->limit(500, '')->toString(),
        ];
    }
}
