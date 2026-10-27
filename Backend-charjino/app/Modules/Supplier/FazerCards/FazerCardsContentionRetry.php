<?php

namespace App\Modules\Supplier\FazerCards;

use Illuminate\Database\QueryException;

final class FazerCardsContentionRetry
{
    public const MAX_ATTEMPTS = 3;

    /**
     * @param  callable(): mixed  $transaction
     * @param  callable(QueryException): void  $onFinalFail
     */
    public static function run(callable $transaction, callable $onFinalFail): mixed
    {
        $attempt = 0;
        while ($attempt < self::MAX_ATTEMPTS) {
            $attempt++;
            try {
                return $transaction();
            } catch (QueryException $e) {
                if (! self::isContention($e) || $attempt >= self::MAX_ATTEMPTS) {
                    if (self::isContention($e)) {
                        $onFinalFail($e);

                        return null;
                    }

                    throw $e;
                }
                self::backoff($attempt);
            }
        }

        return null;
    }

    public static function isContention(QueryException $e): bool
    {
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $driverCode === 1213 || $driverCode === 1020;
    }

    private static function backoff(int $attempt): void
    {
        if (app()->environment('testing')) {
            return;
        }
        usleep(random_int(20_000, 80_000) * max(1, $attempt));
    }
}
