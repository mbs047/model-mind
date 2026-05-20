<?php

namespace Mbs\ModelMind\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Support\Analytics\ModelMindAnalytics;

class RecordModelMindFeedback implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $requestMetadata
     */
    public function __construct(public int $messageId, public array $requestMetadata = []) {}

    public function handle(ModelMindAnalytics $analytics): void
    {
        $message = ModelMindMessage::query()->find($this->messageId);

        if ($message instanceof ModelMindMessage) {
            $analytics->recordFeedbackPayload($message, $this->requestMetadata);
        }
    }
}
