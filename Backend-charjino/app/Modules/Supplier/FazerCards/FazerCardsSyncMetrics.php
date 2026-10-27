<?php

namespace App\Modules\Supplier\FazerCards;

use Illuminate\Support\Facades\Log;

final class FazerCardsSyncMetrics
{
    private static float $startedAt = 0.0;

    private static int $http = 0;

    private static int $httpOk = 0;

    private static int $httpFail = 0;

    private static int $rejected = 0;

    private static bool $lockLost = false;

    public static function start(): void
    {
        self::$startedAt = microtime(true);
        self::$http = 0;
        self::$httpOk = 0;
        self::$httpFail = 0;
        self::$rejected = 0;
        self::$lockLost = false;
    }

    public static function http(int $status): void
    {
        self::$http++;
        if ($status >= 200 && $status < 400) {
            self::$httpOk++;
        } else {
            self::$httpFail++;
        }
    }

    public static function rejected(): void
    {
        self::$rejected++;
    }

    public static function lockLost(): void
    {
        self::$lockLost = true;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function finish(string $scope, string $status, array $extra = []): void
    {
        $durationMs = self::$startedAt > 0 ? (int) round((microtime(true) - self::$startedAt) * 1000) : 0;
        $peak = memory_get_peak_usage(true);
        Log::channel('fazercards')->info('fazercards run', array_merge([
            'scope' => $scope,
            'status' => $status,
            'duration_ms' => $durationMs,
            'peak_memory' => $peak,
            'http' => self::$http,
            'http_ok' => self::$httpOk,
            'http_fail' => self::$httpFail,
            'rejected' => self::$rejected,
            'lock_lost' => self::$lockLost,
        ], $extra));
    }
}
