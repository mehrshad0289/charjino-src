<?php

namespace App\Services\Torob;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TorobV3Cache
{
    public const SCHEMA = 'torob_api_v3_split_sku_v2';

    public const PREFIX = 'torob:v3:';

    public function __construct(private TorobSplitPolicy $policy) {}

    public function schema(): string
    {
        return self::SCHEMA;
    }

    public function ruleFingerprint(): string
    {
        return $this->policy->ruleFingerprint();
    }

    /**
     * @return array{store: string, keys: int, bytes: int|null}
     */
    public function inspect(): array
    {
        try {
            $store = Cache::getStore();
            if ($store instanceof RedisStore) {
                $keys = $this->redisKeys($store);

                return [
                    'store' => 'redis',
                    'keys' => count($keys),
                    'bytes' => $this->redisBytes($store, $keys),
                ];
            }
            if ($store instanceof DatabaseStore) {
                $table = (string) config('cache.stores.database.table', 'cache');
                $rows = DB::table($table)->where('key', 'like', '%'.self::PREFIX.'%')->get();
                $bytes = 0;
                foreach ($rows as $row) {
                    $bytes += strlen((string) ($row->value ?? ''));
                }

                return [
                    'store' => 'database',
                    'keys' => $rows->count(),
                    'bytes' => $bytes,
                ];
            }

            return [
                'store' => class_basename($store),
                'keys' => 0,
                'bytes' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'store' => 'error',
                'keys' => 0,
                'bytes' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function forgetAll(): int
    {
        $store = Cache::getStore();
        if ($store instanceof RedisStore) {
            $keys = $this->redisKeys($store);
            if ($keys === []) {
                return 0;
            }
            $connection = $store->connection();
            $deleted = $connection->del($keys);

            return is_int($deleted) ? $deleted : count($keys);
        }
        if ($store instanceof DatabaseStore) {
            $table = (string) config('cache.stores.database.table', 'cache');

            return (int) DB::table($table)->where('key', 'like', '%'.self::PREFIX.'%')->delete();
        }

        throw new RuntimeException(
            'کش هدفمند torob:v3:* برای store='.class_basename($store).' پشتیبانی نمی‌شود؛ cache:clear سراسری انجام نشد.'
        );
    }

    /**
     * @return list<string>
     */
    private function redisKeys(RedisStore $store): array
    {
        $connection = $store->connection();
        $match = $store->getPrefix().self::PREFIX.'*';
        $keys = [];
        $cursor = '0';
        do {
            $result = $connection->scan($cursor, ['match' => $match, 'count' => 200]);
            if (! is_array($result) || count($result) < 2) {
                break;
            }
            $cursor = (string) $result[0];
            $chunk = $result[1] ?? [];
            if (is_array($chunk)) {
                foreach ($chunk as $key) {
                    $keys[] = (string) $key;
                }
            }
        } while ($cursor !== '0');

        return array_values(array_unique($keys));
    }

    /**
     * @param  list<string>  $keys
     */
    private function redisBytes(RedisStore $store, array $keys): int
    {
        if ($keys === []) {
            return 0;
        }
        $connection = $store->connection();
        $bytes = 0;
        foreach (array_chunk($keys, 50) as $chunk) {
            foreach ($chunk as $key) {
                $size = $connection->executeRaw(['MEMORY', 'USAGE', $key]);
                if (is_numeric($size)) {
                    $bytes += (int) $size;
                }
            }
        }

        return $bytes;
    }
}
