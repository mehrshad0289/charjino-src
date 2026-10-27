<?php

namespace App\Modules\SmartSearch;

final class MatchingStrategyResolver
{
    public static function forQuery(string $normalized): string
    {
        return preg_match('/\d/u', $normalized) === 1 ? 'all' : 'last';
    }
}
