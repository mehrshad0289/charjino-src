<?php

namespace App\Modules\Catalog;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Collection;

final class HomeSections
{
    public const KEY = 'home_sections';

    public const LIMIT_MAX = 24;

    /**
     * @return list<array{group: CatalogGroup, limit: int, productIds: list<int>}>
     */
    public static function storefrontPlan(): array
    {
        $groups = self::visibleGroups();
        $saved = self::saved();
        if ($saved === []) {
            $plan = [];
            foreach ($groups as $group) {
                $plan[] = [
                    'group' => $group,
                    'limit' => self::defaultLimit(),
                    'productIds' => [],
                ];
            }

            return $plan;
        }

        $byId = $groups->keyBy('id');
        $used = [];
        $plan = [];
        foreach ($saved as $row) {
            $id = (int) ($row['groupId'] ?? 0);
            $group = $byId->get($id);
            if (! $group instanceof CatalogGroup) {
                continue;
            }
            $used[$id] = true;
            if (empty($row['enabled'])) {
                continue;
            }
            $plan[] = [
                'group' => $group,
                'limit' => self::limitOf($row['limit'] ?? null),
                'productIds' => self::idsOf($row['productIds'] ?? []),
            ];
        }
        foreach ($groups as $group) {
            if (isset($used[$group->id])) {
                continue;
            }
            $plan[] = [
                'group' => $group,
                'limit' => self::defaultLimit(),
                'productIds' => [],
            ];
        }

        return $plan;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function adminRows(): array
    {
        $groups = self::visibleGroups();
        $saved = [];
        foreach (self::saved() as $row) {
            $id = (int) ($row['groupId'] ?? 0);
            if ($id > 0) {
                $saved[$id] = $row;
            }
        }
        $ordered = [];
        foreach (self::saved() as $row) {
            $id = (int) ($row['groupId'] ?? 0);
            $group = $groups->firstWhere('id', $id);
            if ($group instanceof CatalogGroup) {
                $ordered[] = $group;
            }
        }
        foreach ($groups as $group) {
            if (! isset($saved[$group->id])) {
                $ordered[] = $group;
            }
        }

        $rows = [];
        foreach ($ordered as $group) {
            $row = $saved[$group->id] ?? null;
            $ids = self::idsOf(is_array($row) ? ($row['productIds'] ?? []) : []);
            $picked = $ids === []
                ? collect()
                : Product::query()->whereIn('id', $ids)->get()->keyBy('id');
            $products = [];
            foreach ($ids as $id) {
                $product = $picked->get($id);
                if (! $product) {
                    continue;
                }
                $products[] = [
                    'id' => $product->id,
                    'title' => $product->title,
                    'handle' => $product->handle,
                ];
            }
            $rows[] = [
                'groupId' => $group->id,
                'slug' => $group->slug,
                'title' => $group->storefrontTitle(),
                'enabled' => $row === null ? true : ! empty($row['enabled']),
                'limit' => $row === null ? self::defaultLimit() : self::limitOf($row['limit'] ?? null),
                'productIds' => $ids,
                'products' => $products,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function store(array $rows): void
    {
        $known = self::visibleGroups()->keyBy('id');
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['groupId'] ?? 0);
            if (! $known->has($id)) {
                continue;
            }
            $ids = self::idsOf($row['productIds'] ?? []);
            if ($ids !== []) {
                $ids = Product::query()
                    ->where('catalog_group_id', $id)
                    ->whereIn('id', $ids)
                    ->pluck('id')
                    ->map(fn ($value) => (int) $value)
                    ->all();
                $want = array_flip($ids);
                $ids = array_values(array_filter(self::idsOf($row['productIds'] ?? []), fn (int $productId) => isset($want[$productId])));
            }
            $out[] = [
                'groupId' => $id,
                'enabled' => ! empty($row['enabled']),
                'limit' => self::limitOf($row['limit'] ?? null),
                'productIds' => array_slice($ids, 0, self::LIMIT_MAX),
            ];
        }
        Setting::putValue(self::KEY, json_encode($out, JSON_UNESCAPED_UNICODE));
    }

    public static function defaultLimit(): int
    {
        return self::limitOf(config('charjino.supplier.fazercards_home_per_group', 12));
    }

    /**
     * @return Collection<int, CatalogGroup>
     */
    private static function visibleGroups(): Collection
    {
        return CatalogGroup::query()
            ->where('visible', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function saved(): array
    {
        $decoded = json_decode((string) Setting::getValue(self::KEY, ''), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    private static function limitOf(mixed $value): int
    {
        return max(1, min(self::LIMIT_MAX, (int) $value));
    }

    /**
     * @return list<int>
     */
    private static function idsOf(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $ids = [];
        foreach ($value as $id) {
            $number = (int) $id;
            if ($number > 0 && ! in_array($number, $ids, true)) {
                $ids[] = $number;
            }
        }

        return $ids;
    }
}
