<?php

namespace App\Modules\Supplier\FazerCards;

use App\Exceptions\ApiException;
use App\Support\CustomerError;
use Illuminate\Support\Facades\Cache;

final class FazerCardsSyncStatus
{
    public const CACHE_KEY = 'fazercards_catalog_sync_status';

    public const STATE_KEY = 'fazercards_catalog_sync_state';

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function put(array $payload): void
    {
        Cache::put(self::CACHE_KEY, $payload, now()->addDay());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function putState(array $state): void
    {
        Cache::put(self::STATE_KEY, $state, now()->addDay());
        self::put(self::publicView($state));
    }

    /**
     * @return array<string, mixed>
     */
    public static function state(): array
    {
        $row = Cache::get(self::STATE_KEY);

        return is_array($row) ? $row : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        $row = Cache::get(self::CACHE_KEY);
        if (! is_array($row)) {
            return ['status' => 'idle'];
        }
        unset($row['raw'], $row['seen'], $row['pageItems']);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function publicView(array $state): array
    {
        return [
            'status' => $state['status'] ?? 'idle',
            'phase' => $state['phase'] ?? null,
            'phaseLabel' => $state['phaseLabel'] ?? null,
            'scope' => $state['scope'] ?? null,
            'scopeLabel' => $state['scopeLabel'] ?? null,
            'startedAt' => $state['startedAt'] ?? null,
            'finishedAt' => $state['finishedAt'] ?? null,
            'durationMs' => $state['durationMs'] ?? null,
            'account' => $state['account'] ?? null,
            'groups' => $state['groups'] ?? 0,
            'products' => $state['products'] ?? 0,
            'skus' => $state['skus'] ?? 0,
            'hiddenSkus' => $state['hiddenSkus'] ?? 0,
            'processedItems' => $state['processedItems'] ?? 0,
            'catalogTotal' => $state['catalogTotal'] ?? null,
            'pricing' => $state['pricing'] ?? null,
            'syncIncomplete' => $state['syncIncomplete'] ?? [],
            'message' => $state['message'] ?? null,
            'retryAfterSec' => self::retryAfterSec($state),
        ];
    }

    public const CANCEL_KEY = 'fazercards_catalog_sync_cancel';

    /**
     * @param  array<string, mixed>  $state
     */
    public static function retryAfterSec(array $state): int
    {
        $until = trim((string) ($state['retryAfterAt'] ?? ''));
        if ($until === '') {
            return 0;
        }
        try {
            return max(0, \Carbon\Carbon::parse($until)->getTimestamp() - time());
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function markCancelled(): array
    {
        Cache::forget(self::CANCEL_KEY);
        $state = self::state();
        $state['status'] = 'cancelled';
        $state['finishedAt'] = now()->toIso8601String();
        $state['message'] = 'همگام لغو شد. می‌توانی دوباره همان دسته را بزنی.';
        $state['pageItems'] = [];
        unset($state['retryAfterAt'], $state['errorCode']);
        self::putState($state);

        return self::publicView($state);
    }

    public static function requestCancel(): void
    {
        Cache::put(self::CANCEL_KEY, true, now()->addMinutes(5));
    }

    public static function cancelRequested(): bool
    {
        return (bool) Cache::get(self::CANCEL_KEY);
    }

    public static function fail(string $code, string $message, int $status = 503): never
    {
        $payload = [
            'status' => 'failed',
            'finishedAt' => now()->toIso8601String(),
            'errorCode' => $code,
            'message' => CustomerError::message($code, $message),
        ];
        self::putState($payload);

        throw new ApiException(CustomerError::message($code, $message), $status, $code !== '' ? $code : 'SUPPLIER_UNAVAILABLE');
    }
}
