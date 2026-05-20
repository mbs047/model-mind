<?php

namespace Mbs\ModelMind\Events;

use Mbs\ModelMind\Models\ModelMindMessage;
use Mbs\ModelMind\Models\ModelMindSession;

class AnswerGenerated
{
    /**
     * @param  array<int, array{label: string, url: string, kind: string}>  $actions
     * @param  array<int, array{model: string, record: string, source: string, columns: array<int, string>, action: array{label: string, url: string, kind: string}|null}>  $citations
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public readonly ModelMindSession $session,
        public readonly ModelMindMessage $message,
        public readonly string $question,
        public readonly string $answer,
        public readonly array $actions = [],
        public readonly array $citations = [],
        public readonly array $providerMetadata = [],
        public readonly int $latencyMs = 0,
    ) {}
}
