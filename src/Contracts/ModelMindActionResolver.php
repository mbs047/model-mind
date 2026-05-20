<?php

namespace Mbs\ModelMind\Contracts;

interface ModelMindActionResolver
{
    /**
     * @return array{label: string, url: string, kind: string}|null
     */
    public function resolve(string $url): ?array;
}
