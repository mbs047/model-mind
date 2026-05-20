<?php

namespace Mbs\ModelMind\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Models\ModelMindSession;
use Mbs\ModelMind\Support\Analytics\ModelMindAnalytics;

class RecordModelMindActionClick implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $requestMetadata
     */
    public function __construct(
        public ?int $sessionId,
        public ?int $messageId,
        public array $payload,
        public array $requestMetadata = [],
    ) {}

    public function handle(ModelMindAnalytics $analytics): void
    {
        $session = $this->sessionId
            ? ModelMindSession::query()->find($this->sessionId)
            : null;
        $message = $this->messageId
            ? ModelMindMessage::query()->find($this->messageId)
            : null;

        $analytics->recordActionClickPayload(
            $session instanceof ModelMindSession ? $session : null,
            $message instanceof ModelMindMessage ? $message : null,
            $this->payload,
            $this->requestMetadata,
        );
    }
}
