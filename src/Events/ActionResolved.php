<?php

namespace Mbs\ModelMind\Events;

class ActionResolved
{
    /**
     * @param  array{label: string, url: string, kind: string}  $action
     * @param  array<string, string>  $parameters
     * @param  array<string, mixed>  $definition
     */
    public function __construct(
        public readonly string $key,
        public readonly array $action,
        public readonly array $parameters = [],
        public readonly array $definition = [],
    ) {}
}
