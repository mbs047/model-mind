<?php

namespace Mbs\ModelMind\Support\Database;

class TableNames
{
    public static function sessions(): string
    {
        return self::prefix().'sessions';
    }

    public static function messages(): string
    {
        return self::prefix().'messages';
    }

    public static function memories(): string
    {
        return self::prefix().'memories';
    }

    private static function prefix(): string
    {
        $prefix = config('model-mind.database.table_prefix', 'model_mind_');
        $prefix = is_string($prefix) ? $prefix : 'model_mind_';
        $prefix = preg_replace('/[^A-Za-z0-9_]/', '_', $prefix) ?: 'model_mind_';

        return trim($prefix, '_') === '' ? 'model_mind_' : $prefix;
    }
}
