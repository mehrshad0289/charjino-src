<?php

namespace App\Modules\Supplier\FazerCards;

use Illuminate\Support\Facades\Cache;

final class FazerCardsValidateId
{
    /**
     * Last documented GET /topups/validate-id ids, for tests that simulate a catalog.
     * Production mode never falls back to this list.
     *
     * @var list<string>
     */
    public const SUPPORTED = [
        'pubg_mobile',
        'free_fire',
        'mobile_legends',
        'magic_chess_gogo_global',
        'magic_chess_gogo_ru',
    ];

    /** @var array<string, string> */
    private const EXACT = [
        'pubg_mobile' => 'pubg_mobile',
        'pubg_mobile_auto' => 'pubg_mobile',
        'pubg_mobile_fast' => 'pubg_mobile',
        'pubg_mobile_manual' => 'pubg_mobile',
        'pubg_mobile_reserve' => 'pubg_mobile',
        'free_fire' => 'free_fire',
        'mobile_legends' => 'mobile_legends',
        'magic_chess_gogo_global' => 'magic_chess_gogo_global',
        'magic_chess_gogo_ru' => 'magic_chess_gogo_ru',
    ];

    /**
     * @param  list<string>  $supportedIds
     */
    public static function resolve(string $orderCategoryId, array $supportedIds): ?string
    {
        $id = trim($orderCategoryId);
        if ($id === '') {
            return null;
        }
        $supported = array_fill_keys($supportedIds, true);
        if (isset($supported[$id])) {
            return $id;
        }
        $mapped = self::EXACT[$id] ?? null;
        if (is_string($mapped) && isset($supported[$mapped])) {
            return $mapped;
        }
        if (preg_match('/^pubg_mobile(_|$)/', $id) && isset($supported['pubg_mobile'])) {
            return 'pubg_mobile';
        }
        if (preg_match('/^free_fire(_|$)/', $id) && isset($supported['free_fire'])) {
            return 'free_fire';
        }
        if (str_starts_with($id, 'mobile_legends_')
            && $id !== 'mobile_legends_adventure'
            && isset($supported['mobile_legends'])) {
            return 'mobile_legends';
        }

        return null;
    }

    /**
     * Category ids from GET /topups/validate-id (900s cache). Empty if the list is missing.
     *
     * @return list<string>
     */
    public static function supportedIds(): array
    {
        try {
            if (function_exists('app') && app()->bound('cache')) {
                $cached = Cache::get('fazer:validate-id:catalog');
                if (is_array($cached)) {
                    return self::idsFromCatalog($cached);
                }
                $client = app(FazerCardsClient::class);
                if ($client->configured()) {
                    return self::idsFromCatalog($client->validateIdCatalog());
                }
            }
        } catch (\Throwable) {
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    public static function idsFromCatalog(array $items): array
    {
        $ids = [];
        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['category_id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public static function mode(?string $orderCategoryId): string
    {
        $id = trim((string) $orderCategoryId);
        if ($id === '') {
            return 'manual';
        }

        return self::resolve($id, self::supportedIds()) ? 'api' : 'manual';
    }

    public static function catalogLoaded(): bool
    {
        try {
            if (function_exists('app') && app()->bound('cache')) {
                return is_array(Cache::get('fazer:validate-id:catalog'));
            }
        } catch (\Throwable) {
        }

        return false;
    }

    public static function needsAck(?string $orderCategoryId): bool
    {
        $id = trim((string) $orderCategoryId);
        if ($id === '') {
            return false;
        }
        $ids = self::supportedIds();
        if ($ids === [] && ! self::catalogLoaded()) {
            return false;
        }

        return self::resolve($id, $ids) === null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    public static function fieldKeys(array $item): array
    {
        $fields = $item['fields'] ?? [];
        if (! is_array($fields)) {
            return [];
        }
        $keys = [];
        foreach ($fields as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function pickFields(array $keys, array $input): array
    {
        $out = [];
        foreach ($keys as $key) {
            $value = $input[$key] ?? null;
            if ($value === null || $value === '') {
                $alias = strtolower($key);
                if (in_array($alias, ['player_id', 'playerid', 'uid', 'user', 'userid', 'user_id'], true)) {
                    $value = $input['player_id'] ?? $input['playerId'] ?? $input['uid'] ?? null;
                } elseif (in_array($alias, ['zone_id', 'zoneid'], true)) {
                    $value = $input['zone_id'] ?? $input['zoneId'] ?? $input['zoneid'] ?? null;
                }
            }
            if ($value === null || $value === '') {
                continue;
            }
            $out[$key] = trim((string) $value);
        }

        return $out;
    }
}
