<?php

namespace Mbs\ModelMind\Events;

use Mbs\ModelMind\Models\ModelMindMessage;

class FeedbackSubmitted
{
    public function __construct(
        public readonly ModelMindMessage $message,
        public readonly string $feedback,
        public readonly ?string $note = null,
    ) {}
}
