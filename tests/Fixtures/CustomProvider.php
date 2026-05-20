<?php

namespace Mbs\ModelMind\Tests\Fixtures;

use Mbs\ModelMind\Contracts\ModelMindProvider;
use Mbs\ModelMind\Data\ModelMindRequestData;
use Mbs\ModelMind\Data\ModelMindResponseData;

class CustomProvider implements ModelMindProvider
{
    public function answer(ModelMindRequestData $request): ModelMindResponseData
    {
        return new ModelMindResponseData('Custom provider answer.', [
            'provider' => 'custom',
        ]);
    }
}
