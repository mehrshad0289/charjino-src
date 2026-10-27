<?php

namespace App\Modules\Supplier\FazerCards;

final class FazerCardsRequestLane
{
    public const ORDER = 'order';

    public const HEAVY = 'heavy';

    public const LIGHT = 'light';

    private static string $lane = self::ORDER;

    public static function current(): string
    {
        return self::$lane;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    public static function using(string $lane, callable $fn): mixed
    {
        $prev = self::$lane;
        self::$lane = $lane;
        try {
            return $fn();
        } finally {
            self::$lane = $prev;
        }
    }
}
