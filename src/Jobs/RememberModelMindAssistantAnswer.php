<?php

namespace Mbs\ModelMind\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Support\Learning\LearningRepository;

class RememberModelMindAssistantAnswer implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $messageId, public string $question) {}

    public function handle(LearningRepository $learning): void
    {
        $message = ModelMindMessage::query()->find($this->messageId);

        if (! $message instanceof ModelMindMessage || ! $message->isAssistant()) {
            return;
        }

        $learning->rememberAssistantAnswer($message->content, [
            'message_id' => $message->uuid,
            'session_id' => $message->session?->uuid,
            'question' => $this->question,
        ]);
    }
}
