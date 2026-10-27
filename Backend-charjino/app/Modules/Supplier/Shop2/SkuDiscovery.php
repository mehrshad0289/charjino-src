<?php

namespace App\Modules\Supplier\Shop2;

use App\Support\Money;

final class SkuDiscovery
{
    public function __construct(private readonly Shop2Client $shop2) {}

    /**
     * @return array<string, mixed>
     */
    public function discover(): array
    {
        $pubg = [];
        $cod = [];

        foreach ($this->shop2->bigCategories() as $big) {
            $game = self::gameOf(Payload::name($big));
            if ($game === null) {
                continue;
            }
            $bigId = Payload::id($big, 'id', 'big_category_id');
            if (! $bigId) {
                continue;
            }
            $this->pause();

            foreach ($this->shop2->categories($bigId) as $category) {
                $categoryId = Payload::id($category, 'id', 'category_id');
                if (! $categoryId) {
                    continue;
                }

                $this->pause();
                $items = [];
                foreach ($this->shop2->subcategories($categoryId) as $item) {
                    $itemId = Payload::id($item, 'item_id', 'id', 'sub_category_id');
                    if (! $itemId) {
                        continue;
                    }
                    $items[] = [
                        'itemId' => $itemId,
                        'name' => Payload::name($item),
                        'usd' => Payload::usdPrice($item),
                        'typed' => self::itemMatchesGame($game, Payload::name($item)),
                    ];
                }
                $typed = array_values(array_filter($items, fn (array $row) => $row['typed']));
                $items = $typed !== [] ? $typed : $items;
                if ($items === []) {
                    continue;
                }

                $this->pause();
                $requirements = [];
                try {
                    $requirements = $this->shop2->requirements($categoryId);
                } catch (\Throwable) {
                    $requirements = [];
                }

                $picked = $this->pickItems($items);
                foreach ($picked as $row) {
                    $usd = $row['usd'];
                    if (! Money::isPositive($usd)) {
                        $this->pause();
                        try {
                            $usd = $this->shop2->price($row['itemId'])['unit_price'] ?? '0';
                        } catch (\Throwable) {
                            $usd = '0';
                        }
                    }

                    $entry = [
                        'internal_code' => self::internalCode($game, $row['name'], $row['itemId']),
                        'game' => $game,
                        'product_type' => $game === 'PUBG_MOBILE' ? 'UC' : 'CP',
                        'denomination_label' => $row['name'],
                        'category_id' => $categoryId,
                        'sub_category_id' => $row['itemId'],
                        'item_id' => $row['itemId'],
                        'unit_price_usd' => Money::string($usd),
                        'requirements_schema_ref' => 'category:'.$categoryId,
                        'requirement_keys' => self::requirementKeys($requirements),
                        'test_status' => 'pending',
                    ];

                    if ($game === 'PUBG_MOBILE') {
                        $pubg[] = $entry;
                    } else {
                        $cod[] = $entry;
                    }
                }
            }
        }

        $map = [
            'provider' => 'shop2topup',
            'currency' => 'USD',
            'updated_at' => now()->toIso8601String(),
            'skus' => array_values(array_merge(
                array_slice($pubg, 0, 2),
                array_slice($cod, 0, 2),
            )),
        ];

        SkuMap::write($map);

        return $map;
    }

    /**
     * @param  list<array{itemId: int, name: string, usd: string}>  $items
     * @return list<array{itemId: int, name: string, usd: string}>
     */
    private function pickItems(array $items): array
    {
        usort($items, fn (array $a, array $b) => Money::compare($a['usd'], $b['usd']));
        $cheapest = $items[0];
        $out = [$cheapest];
        foreach (array_reverse($items) as $row) {
            if ($row['itemId'] !== $cheapest['itemId']) {
                $out[] = $row;
                break;
            }
        }

        return $out;
    }

    private function pause(): void
    {
        if (app()->environment('testing')) {
            return;
        }
        usleep(200000);
    }

    private static function gameOf(string $name): ?string
    {
        if (preg_match('/pubg/i', $name)) {
            return 'PUBG_MOBILE';
        }
        if (preg_match('/call\s*of\s*duty|\bcodm\b|\bcod\b/i', $name)) {
            return 'COD_MOBILE';
        }

        return null;
    }

    private static function itemMatchesGame(string $game, string $itemName): bool
    {
        if ($game === 'PUBG_MOBILE') {
            return (bool) preg_match('/\buc\b|unknown\s*cash/i', $itemName);
        }

        return (bool) preg_match('/\bcp\b|cod\s*points/i', $itemName);
    }

    private static function internalCode(string $game, string $label, int $itemId): string
    {
        $prefix = $game === 'PUBG_MOBILE' ? 'SKU_PUBG_UC' : 'SKU_COD_CP';
        if (preg_match('/(\d[\d,]*)/', $label, $match)) {
            $amount = str_replace(',', '', $match[1]);

            return $prefix.'_'.$amount;
        }

        return $prefix.'_'.$itemId;
    }

    /**
     * @param  list<array<string, mixed>>  $requirements
     * @return list<string>
     */
    private static function requirementKeys(array $requirements): array
    {
        $keys = [];
        foreach ($requirements as $row) {
            if (is_string($row)) {
                $name = $row;
            } else {
                $name = (string) ($row['field_name'] ?? $row['name'] ?? '');
            }
            $name = trim($name);
            if ($name === '' || ForbiddenBuyerField::matches($name)) {
                continue;
            }
            $keys[] = $name;
        }

        return array_values(array_unique($keys));
    }
}
