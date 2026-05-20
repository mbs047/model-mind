<?php

namespace Mbs\ModelMind\Tests\Fixtures;

use Mbs\ModelMind\Contracts\ModelMindVectorSearcher;

class FakeVectorSearcher implements ModelMindVectorSearcher
{
    /**
     * @var array<int, int|string>
     */
    public static array $keys = [];

    public function search(string $question, string $modelClass, array $settings, array $columns, int $limit): iterable
    {
        return array_slice(self::$keys, 0, $limit);
    }
}
