<?php

namespace Mbs\ModelMind\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Models\ModelMindSession;
use Mbs\ModelMind\Support\Analytics\ModelMindAnalytics;

class RecordModelMindChatCompleted implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $providerMetadata
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public int $sessionId,
        public int $messageId,
        public array $providerMetadata,
        public int $latencyMs,
        public array $context = [],
    ) {}

    public function handle(ModelMindAnalytics $analytics): void
    {
        $session = ModelMindSession::query()->find($this->sessionId);
        $message = ModelMindMessage::query()->find($this->messageId);

        if (! $session instanceof ModelMindSession || ! $message instanceof ModelMindMessage) {
            return;
        }

        $analytics->recordChatCompleted($session, $message, $this->providerMetadata, $this->latencyMs, $this->context);
    }
}
