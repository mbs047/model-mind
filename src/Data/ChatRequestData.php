<?php

namespace Mbs\LaravelAiChat\Data;

use Mbs\LaravelAiChat\Models\MbsAiChatSession;

class ChatRequestData
{
    public function __construct(
        public readonly string $question,
        public readonly string $instructions,
        public readonly MbsAiChatSession $session,
    ) {}
}
