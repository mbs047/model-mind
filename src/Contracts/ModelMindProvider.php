<?php

namespace Mbs\ModelMind\Contracts;

use Mbs\ModelMind\Data\ModelMindRequestData;
use Mbs\ModelMind\Data\ModelMindResponseData;

interface ModelMindProvider
{
    public function answer(ModelMindRequestData $request): ModelMindResponseData;
}
