<?php

namespace Mbs\ModelMind\Tests\Fixtures;

use Mbs\ModelMind\Contracts\ModelMindProvider;
use Mbs\ModelMind\Contracts\StreamingModelMindProvider;
use Mbs\ModelMind\Data\ModelMindRequestData;
use Mbs\ModelMind\Data\ModelMindResponseData;

class StreamingProvider implements ModelMindProvider, StreamingModelMindProvider
{
    /**
     * @var array<int, string>
     */
    public static array $chunks = [
        'Streaming ',
        'answer ',
        'with a route.',
    ];

    public function answer(ModelMindRequestData $request): ModelMindResponseData
    {
        return new ModelMindResponseData('Fallback answer.', [
            'model' => 'test-stream',
        ]);
    }

    /**
     * @return iterable<int, string>
     */
    public function stream(ModelMindRequestData $request): iterable
    {
        foreach (self::$chunks as $chunk) {
            yield $chunk;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function streamMetadata(ModelMindRequestData $request): array
    {
        return [
            'model' => 'test-stream',
        ];
    }
}
