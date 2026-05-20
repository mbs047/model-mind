<?php

namespace Mbs\ModelMind\Data;

class ModelMindResponseData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $answer,
        public readonly array $metadata = [],
    ) {}
}
