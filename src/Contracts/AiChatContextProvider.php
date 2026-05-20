<?php

namespace Mbs\LaravelAiChat\Contracts;

interface AiChatContextProvider
{
    /**
     * @return array<string, mixed>
     */
    public function context(): array;
}
