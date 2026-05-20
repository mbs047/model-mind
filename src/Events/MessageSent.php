<?php

namespace Mbs\ModelMind\Events;

use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Models\ModelMindSession;

class MessageSent
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly ModelMindSession $session,
        public readonly ModelMindMessage $message,
        public readonly string $question,
        public readonly array $context = [],
    ) {}
}
