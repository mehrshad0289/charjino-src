<?php

namespace App\Modules\Supplier\FazerCards;

use App\Exceptions\ApiException;

final class FazerCardsBilling
{
    /** @var list<string> */
    public const PLANS = ['bronze', 'silver', 'gold'];

    public static function plan(string $raw): string
    {
        $plan = strtolower(trim($raw));
        if (! in_array($plan, self::PLANS, true)) {
            throw new ApiException('پلن باید bronze، silver یا gold باشد.', 400, 'INVALID_PLAN');
        }

        return $plan;
    }

    public static function page(mixed $page): int
    {
        $n = (int) $page;

        return $n < 1 ? 1 : $n;
    }

    public static function limit(mixed $limit, int $default = 50): int
    {
        $n = (int) $limit;
        if ($n <= 0) {
            $n = $default;
        }

        return min(100, $n);
    }

    public static function flag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function rank(string $plan): int
    {
        $i = array_search(strtolower(trim($plan)), self::PLANS, true);

        return is_int($i) ? $i : -1;
    }

    public static function isUpgrade(string $from, string $to): bool
    {
        $a = self::rank($from);
        $b = self::rank($to);

        return $a >= 0 && $b > $a;
    }
}
