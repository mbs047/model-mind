<?php

namespace Mbs\ModelMind\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ModelMindVectorSearcher
{
    /**
     * Return matching model references for a question.
     *
     * References may be Eloquent models, primary keys, or arrays containing
     * `id`, `key`, `model`, or `record`. ModelMind will re-query primary keys
     * through the normal authorized context query before building prompt rows.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $settings
     * @param  array<int, string>  $columns
     * @return iterable<int, Model|int|string|array<string, mixed>>
     */
    public function search(string $question, string $modelClass, array $settings, array $columns, int $limit): iterable;
}
