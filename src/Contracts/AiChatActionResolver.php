<?php

namespace Mbs\LaravelAiChat\Contracts;

interface AiChatActionResolver
{
    /**
     * @return array{label: string, url: string, kind: string}|null
     */
    public function resolve(string $url): ?array;
}
