<?php

namespace App\Modules\Supplier\Shop2;

use App\Support\Money;

final class SkuMap
{
    /**
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        return [
            'provider' => 'shop2topup',
            'currency' => 'USD',
            'updated_at' => null,
            'skus' => [],
        ];
    }

    public static function storagePath(): string
    {
        return storage_path('app/supplier/sku-map.json');
    }

    public static function templatePath(): string
    {
        return config_path('sku-map.json');
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(): array
    {
        $storage = self::decodeFile(self::storagePath());
        $template = self::decodeFile(self::templatePath());

        if ($template && self::hasMvpIds($template) && ! self::hasMvpIds($storage ?? [])) {
            return $template + self::empty();
        }

        if ($storage) {
            return $storage + self::empty();
        }

        if ($template) {
            return $template + self::empty();
        }

        return self::empty();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeFile(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private static function hasMvpIds(array $map): bool
    {
        $ids = [];
        foreach ($map['skus'] ?? [] as $row) {
            if (is_array($row)) {
                $ids[] = (int) ($row['item_id'] ?? 0);
            }
        }

        return in_array(12, $ids, true) && in_array(1002, $ids, true);
    }

    /**
     * @param  array<string, mixed>  $map
     */
    public static function write(array $map): string
    {
        $dir = dirname(self::storagePath());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $payload = [
            'provider' => 'shop2topup',
            'currency' => 'USD',
            'updated_at' => $map['updated_at'] ?? now()->toIso8601String(),
            'skus' => array_values($map['skus'] ?? []),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            $json = json_encode(self::empty(), JSON_PRETTY_PRINT);
        }

        file_put_contents(self::storagePath(), $json.PHP_EOL);

        return self::storagePath();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function skus(): array
    {
        $rows = self::read()['skus'] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $internalCode): ?array
    {
        foreach (self::skus() as $row) {
            if (($row['internal_code'] ?? '') === $internalCode) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Cheapest one PUBG UC + one COD CP. Storefront stays closed on other denominations until live tests pass.
     *
     * @param  list<array<string, mixed>>  $skus
     * @return list<array{id: int, category_id: int, price: string}>
     */
    public static function cheapestPicks(array $skus): array
    {
        $best = [];
        foreach ($skus as $row) {
            if (! is_array($row)) {
                continue;
            }
            $game = (string) ($row['game'] ?? '');
            $itemId = (int) ($row['item_id'] ?? 0);
            if ($itemId < 1 || ($game !== 'PUBG_MOBILE' && $game !== 'COD_MOBILE')) {
                continue;
            }
            $price = (string) ($row['unit_price_usd'] ?? '0');
            if (! isset($best[$game]) || Money::compare($price, $best[$game]['price']) < 0) {
                $best[$game] = [
                    'id' => $itemId,
                    'category_id' => (int) ($row['category_id'] ?? 0),
                    'price' => $price,
                ];
            }
        }

        return array_values($best);
    }

    /**
     * @param  list<array<string, mixed>>  $skus
     * @return list<int>
     */
    public static function cheapestItemIds(array $skus): array
    {
        return array_values(array_map(static fn (array $row): int => $row['id'], self::cheapestPicks($skus)));
    }

    /**
     * @param  list<array<string, mixed>>  $skus
     * @return list<int>
     */
    public static function cheapestCategoryIds(array $skus): array
    {
        $ids = [];
        foreach (self::cheapestPicks($skus) as $row) {
            if ($row['category_id'] > 0) {
                $ids[] = $row['category_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    public static function mvpStorefrontItemIds(): array
    {
        return self::cheapestItemIds(self::skus());
    }

    /**
     * @return list<int>
     */
    public static function mvpCategoryIds(): array
    {
        return self::cheapestCategoryIds(self::skus());
    }
}
