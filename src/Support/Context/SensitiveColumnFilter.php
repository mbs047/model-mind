<?php

namespace Mbs\LaravelAiChat\Support\Context;

class SensitiveColumnFilter
{
    /**
     * @param  array<int, string>  $explicitlyAllowed
     * @param  array<int, string>  $explicitlyBlocked
     */
    public function allowed(string $column, ?string $cast = null, array $explicitlyAllowed = [], array $explicitlyBlocked = []): bool
    {
        if (in_array($column, $explicitlyBlocked, true)) {
            return false;
        }

        if (in_array($column, $explicitlyAllowed, true)) {
            return true;
        }

        if (in_array($column, (array) config('mbs-ai-chat.security.blocked_columns', []), true)) {
            return false;
        }

        foreach ((array) config('mbs-ai-chat.security.blocked_patterns', []) as $pattern) {
            if (@preg_match($pattern, $column) === 1) {
                return false;
            }
        }

        if ($cast && in_array($cast, (array) config('mbs-ai-chat.security.blocked_casts', []), true)) {
            return false;
        }

        return true;
    }
}
