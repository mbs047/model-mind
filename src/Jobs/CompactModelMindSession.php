<?php

namespace Mbs\ModelMind\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mbs\ModelMind\Models\ModelMindSession;

class CompactModelMindSession implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $sessionId) {}

    public function handle(): void
    {
        $session = ModelMindSession::query()->find($this->sessionId);

        if ($session instanceof ModelMindSession) {
            $session->compactForPrompt();
        }
    }
}
