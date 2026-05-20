<?php

namespace Mbs\ModelMind\Data;

use Mbs\ModelMind\Models\ModelMindSession;

class ModelMindRequestData
{
    public function __construct(
        public readonly string $question,
        public readonly string $instructions,
        public readonly ModelMindSession $session,
    ) {}
}
