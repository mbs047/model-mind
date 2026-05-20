<?php

namespace Mbs\ModelMind\Events;

use Mbs\ModelMind\Models\ModelMindMemory;

class MemoryLearned
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly ModelMindMemory $memory,
        public readonly bool $created,
        public readonly array $metadata = [],
    ) {}
}
