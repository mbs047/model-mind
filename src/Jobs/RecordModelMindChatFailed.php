<?php

namespace Mbs\ModelMind\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Models\ModelMindSession;
use Mbs\ModelMind\Support\Analytics\ModelMindAnalytics;

class RecordModelMindChatFailed implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $sessionId,
        public int $messageId,
        public string $errorClass,
        public string $errorMessage,
        public int $latencyMs,
    ) {}

    public function handle(ModelMindAnalytics $analytics): void
    {
        $session = ModelMindSession::query()->find($this->sessionId);
        $message = ModelMindMessage::query()->find($this->messageId);

        if (! $session instanceof ModelMindSession || ! $message instanceof ModelMindMessage) {
            return;
        }

        $analytics->recordChatFailedPayload($session, $message, $this->errorClass, $this->errorMessage, $this->latencyMs);
    }
}
