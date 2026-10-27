<?php

namespace App\Modules\Supplier\FazerCards;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class FazerCardsRateLimiter
{
    public const WINDOW_SECONDS = 60;

    /**
     * @var array<string, mixed>
     */
    public static array $lastObserved = [];

    public static int $blocked = 0;

    /**
     * @param  array<string, mixed>  $headers
     */
    public static function observe(array $headers): void
    {
        self::$lastObserved = $headers;
        FazerCardsSyncMetrics::http((int) ($headers['status'] ?? 0));
        $limit = isset($headers['limit']) && is_numeric($headers['limit']) ? (int) $headers['limit'] : 0;
        $remaining = isset($headers['remaining']) && is_numeric($headers['remaining']) ? (int) $headers['remaining'] : null;
        $catalogish = (bool) ($headers['catalogish'] ?? false);
        if ($catalogish && $limit >= 100) {
            Cache::put('fazercards:rl:catalog_limit', $limit, 120);
            if ($remaining !== null) {
                Cache::put('fazercards:rl:catalog_remaining', $remaining, 120);
            }
        }
        $retryAfter = isset($headers['retryAfter']) ? (int) $headers['retryAfter'] : 0;
        $status = (int) ($headers['status'] ?? 0);
        if ($status === 429 && $retryAfter > 0) {
            Cache::put('fazercards:rl:retry_until', now()->addSeconds($retryAfter)->getTimestamp(), $retryAfter + 5);
        }
    }

    public static function before(string $lane): void
    {
        if ($lane === FazerCardsRequestLane::ORDER) {
            return;
        }
        if (app()->environment('testing') && ! config('charjino.supplier.fazercards_rate_limiter_enforce')) {
            return;
        }

        while (true) {
            $retryUntil = (int) Cache::get('fazercards:rl:retry_until', 0);
            $now = time();
            if ($retryUntil > $now) {
                self::wait($retryUntil - $now);

                continue;
            }
            $limit = max(1, (int) Cache::get('fazercards:rl:catalog_limit', 300));
            $remaining = Cache::get('fazercards:rl:catalog_remaining');
            if ($remaining !== null && $limit > 0 && (int) $remaining < (int) floor($limit * 0.1)) {
                self::wait(1);

                continue;
            }
            $heavy = self::windowCount(FazerCardsRequestLane::HEAVY);
            $light = self::windowCount(FazerCardsRequestLane::LIGHT);
            $heavyCap = (int) floor($limit * 0.5);
            $lightCap = (int) floor($limit * 0.2);
            $syncCap = (int) floor($limit * 0.7);
            if ($lane === FazerCardsRequestLane::HEAVY && $heavy >= $heavyCap) {
                self::wait(1);

                continue;
            }
            if ($lane === FazerCardsRequestLane::LIGHT && $light >= $lightCap) {
                self::wait(1);

                continue;
            }
            if (($heavy + $light) >= $syncCap) {
                self::wait(1);

                continue;
            }

            self::incrementWindow($lane);

            return;
        }
    }

    public static function windowCount(string $lane): int
    {
        return (int) Cache::get(self::windowKey($lane), 0);
    }

    public static function catalogLimit(): int
    {
        return max(1, (int) Cache::get('fazercards:rl:catalog_limit', 300));
    }

    private static function incrementWindow(string $lane): void
    {
        $key = self::windowKey($lane);
        if (Cache::add($key, 0, self::WINDOW_SECONDS + 10)) {
            Cache::put($key, 0, self::WINDOW_SECONDS + 10);
        }
        Cache::increment($key);
    }

    private static function windowKey(string $lane): string
    {
        $bucket = (int) floor(time() / self::WINDOW_SECONDS);

        return 'fazercards:rl:win:'.$bucket.':'.$lane;
    }

    private static function wait(int $seconds): void
    {
        self::$blocked++;
        FazerCardsSyncMetrics::rejected();
        if (app()->environment('testing')) {
            throw new ApiException('محدودیت نرخ FazerCards.', 429, 'RATE_LIMIT_EXCEEDED');
        }
        $seconds = max(1, min(30, $seconds));
        Log::channel('fazercards')->info('fazercards rate wait', [
            'seconds' => $seconds,
            'lane' => FazerCardsRequestLane::current(),
        ]);
        sleep($seconds);
    }
}
