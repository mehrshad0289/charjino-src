<?php

namespace App\Modules\Catalog;

use App\Exceptions\ApiException;
use App\Models\CatalogGroup;
use App\Support\CustomerError;
use Illuminate\Support\Facades\Cache;

class CatalogSyncStatus
{
    public const CACHE_KEY = 'catalog_sync_status';

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function put(array $payload): void
    {
        Cache::put(self::CACHE_KEY, $payload, now()->addDay());
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
        unset($row['raw']);
        if (isset($row['account']) && is_array($row['account'])) {
            unset($row['account']['raw']);
            $row['account'] = self::publicAccount($row['account']);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $account
     * @return array{email: string, wallet: string, currency: string, active: bool}
     */
    public static function publicAccount(array $account): array
    {
        return [
            'email' => (string) ($account['email'] ?? ''),
            'wallet' => (string) ($account['wallet'] ?? '0'),
            'currency' => (string) ($account['currency'] ?? 'USD'),
            'active' => (bool) ($account['active'] ?? true),
        ];
    }

    public static function fail(string $code, string $message, int $status = 503): never
    {
        self::put([
            'status' => 'failed',
            'finishedAt' => now()->toIso8601String(),
            'errorCode' => $code,
            'message' => CustomerError::message($code, $message),
        ]);

        throw new ApiException(CustomerError::message($code, $message), $status, $code !== '' ? $code : 'SUPPLIER_UNAVAILABLE');
    }
}
