<?php

namespace Mbs\ModelMind\Contracts;

interface ModelMindContextProvider
{
    /**
     * @return array<string, mixed>
     */
    public function context(): array;
}
