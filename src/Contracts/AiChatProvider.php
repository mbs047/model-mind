<?php

namespace Mbs\LaravelAiChat\Contracts;

use Mbs\LaravelAiChat\Data\ChatRequestData;
use Mbs\LaravelAiChat\Data\ChatResponseData;

interface AiChatProvider
{
    public function answer(ChatRequestData $request): ChatResponseData;
}
