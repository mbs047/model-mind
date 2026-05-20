<?php

namespace Mbs\ModelMind\Contracts;

use Mbs\ModelMind\Data\ModelMindRequestData;

interface StreamingModelMindProvider
{
    /**
     * @return iterable<int, string>
     */
    public function stream(ModelMindRequestData $request): iterable;

    /**
     * @return array<string, mixed>
     */
    public function streamMetadata(ModelMindRequestData $request): array;
}
