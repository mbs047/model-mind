<?php

namespace Mbs\LaravelAiChat\Data;

class ChatResponseData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $answer,
        public readonly array $metadata = [],
    ) {}
}
