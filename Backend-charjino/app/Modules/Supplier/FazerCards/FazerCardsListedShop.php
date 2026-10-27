<?php

namespace App\Modules\Supplier\FazerCards;

use App\Models\Product;
use App\Modules\Catalog\GameKeyPlatform;

final class FazerCardsListedShop
{
    public const SHOP_MAX_CEILING = 2000;

    public int $lastRemoteTotal = 0;

    public int $lastSelectedTotal = 0;

    /** @var list<string> */
    public const SEARCHABLE = ['topups', 'giftcards', 'gamekeys', 'manual', 'steam-gifts'];

    public function __construct(private readonly FazerCardsClient $client) {}

    public function shopMax(?string $family = null): int
    {
        $listed = max(1, min(self::SHOP_MAX_CEILING, (int) config('charjino.supplier.fazercards_listed_shop_max', 800)));
        if ($family === FazerCardsIds::GAMEKEY) {
            return $this->gamekeysPerPlatform() * GameKeyPlatform::SLOT_CAP;
        }
        // گیفت‌کارت شارژینو: کل کاتالوگ Fazer، نه سقف listed_shop_max.
        if ($family === FazerCardsIds::GIFTCARD || $family === 'giftcards') {
            return self::SHOP_MAX_CEILING;
        }

        return $listed;
    }

    /** @var list<string> */
    private array $lastGamekeyPlatforms = [];

    /**
     * @return list<string>
     */
    public function lastGamekeyPlatforms(): array
    {
        return $this->lastGamekeyPlatforms;
    }

    public function gamekeysPerPlatform(): int
    {
        return max(
            1,
            min(
                GameKeyPlatform::PER_PLATFORM_MAX,
                (int) config('charjino.supplier.fazercards_gamekeys_per_platform', GameKeyPlatform::PER_PLATFORM)
            )
        );
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    public function worklistRows(array $spec): array
    {
        return $this->worklistFromRows($spec, $this->allRows($spec));
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  list<array<string, mixed>>  $all
     * @return list<array<string, mixed>>
     */
    public function worklistFromRows(array $spec, array $all): array
    {
        $family = (string) ($spec['family'] ?? '');
        $this->lastRemoteTotal = max($this->lastRemoteTotal, count($all), (int) ($this->client->lastListTotal ?? 0));
        $max = $this->shopMax($family);
        $gamekeys = $family === FazerCardsIds::GAMEKEY;
        $giftcards = $family === FazerCardsIds::GIFTCARD;
        $per = $gamekeys ? $this->gamekeysPerPlatform() : $max;
        $byId = [];
        foreach ($all as $row) {
            $id = $this->remoteId($spec, $row);
            if ($id !== '') {
                $byId[$id] = $row;
            }
        }
        $selected = [];
        $seen = [];
        $take = function (string $id) use (&$selected, &$seen): void {
            $id = trim($id);
            if ($id === '' || isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
            $selected[] = $id;
        };
        foreach ($this->markedRemoteIds($family) as $id) {
            $take($id);
        }
        foreach ($this->stapleIds($spec) as $id) {
            $take($id);
        }
        foreach ($this->existingRemoteIds($family) as $id) {
            $id = trim($id);
            if ($id === '' || ! isset($byId[$id])) {
                continue;
            }
            $take($id);
        }
        $newCount = 0;
        $newByPlatform = [];
        $addNew = function (string $id) use (&$selected, &$seen, &$newCount, &$newByPlatform, $max, $per, $gamekeys, $byId): void {
            $id = trim($id);
            if ($id === '' || isset($seen[$id])) {
                return;
            }
            $platform = null;
            if ($gamekeys) {
                $row = $byId[$id] ?? null;
                $platform = is_array($row) ? GameKeyPlatform::fromFazer($row) : null;
                if ($platform !== null && ($newByPlatform[$platform] ?? 0) >= $per) {
                    return;
                }
            } elseif ($newCount >= $max) {
                return;
            }
            $seen[$id] = true;
            $selected[] = $id;
            $newCount++;
            if ($platform !== null) {
                $newByPlatform[$platform] = ($newByPlatform[$platform] ?? 0) + 1;
            }
        };
        $ranked = $this->newestFirst($spec, $all);
        if ($giftcards) {
            foreach ($ranked as $row) {
                $take($this->remoteId($spec, $row));
            }
            $rows = $this->rowsForIds($spec, $all, $selected);
            $this->lastSelectedTotal = count($rows);

            return $rows;
        }
        if ($gamekeys) {
            $platformIds = [];
            foreach ($ranked as $row) {
                $platformId = GameKeyPlatform::fromFazer($row);
                if ($platformId !== null) {
                    $platformIds[$platformId] = $platformId;
                }
            }
            foreach (GameKeyPlatform::sortIds(array_values($platformIds)) as $platformId) {
                foreach ($ranked as $row) {
                    if (GameKeyPlatform::fromFazer($row) !== $platformId) {
                        continue;
                    }
                    $addNew($this->remoteId($spec, $row));
                }
            }
        } else {
            foreach ($ranked as $row) {
                $addNew($this->remoteId($spec, $row));
            }
        }

        $rows = $this->rowsForIds($spec, $all, $selected);
        $this->lastSelectedTotal = count($rows);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    public function search(array $spec, string $needle, int $limit = 20, ?string $platform = null): array
    {
        $limit = max(1, min(40, $limit));
        $rows = $this->newestFirst($spec, $this->allRows($spec));
        if (($spec['family'] ?? '') === FazerCardsIds::GAMEKEY) {
            $ids = [];
            foreach ($rows as $row) {
                $platformId = GameKeyPlatform::fromFazer($row);
                if ($platformId !== null) {
                    $ids[$platformId] = $platformId;
                }
            }
            $this->lastGamekeyPlatforms = GameKeyPlatform::sortIds(array_values($ids));
            $want = GameKeyPlatform::canonical($platform);
            if ($want !== null) {
                $rows = array_values(array_filter(
                    $rows,
                    fn (array $row) => GameKeyPlatform::fromFazer($row) === $want
                ));
            }
        }
        $query = mb_strtolower(trim($needle));
        if ($query === '') {
            return array_slice($rows, 0, $limit);
        }
        $hits = [];
        foreach ($rows as $row) {
            $name = mb_strtolower($this->rowName($row));
            $id = mb_strtolower($this->remoteId($spec, $row));
            if ($id === '') {
                continue;
            }
            if (! str_contains($name, $query) && ! str_contains($id, $query)) {
                continue;
            }
            $hits[] = $row;
            if (count($hits) >= $limit) {
                break;
            }
        }

        return $hits;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>|null
     */
    public function findRow(array $spec, string $remoteId): ?array
    {
        $want = trim($remoteId);
        if ($want === '') {
            return null;
        }
        foreach ($this->allRows($spec) as $row) {
            if ($this->remoteId($spec, $row) === $want) {
                return $row;
            }
        }

        return ['id' => $want, 'category_id' => $want, 'game_id' => $want, 'name' => $want];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    public function remoteId(array $spec, array $row): string
    {
        foreach (is_array($spec['idKeys'] ?? null) ? $spec['idKeys'] : ['id'] as $key) {
            $value = trim((string) ($row[(string) $key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function rowName(array $row): string
    {
        foreach (['name', 'GameName', 'title', 'category'] as $key) {
            $value = $row[$key] ?? null;
            if (is_array($value)) {
                $value = $value['name'] ?? '';
            }
            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    public function allRows(array $spec): array
    {
        $path = (string) ($spec['path'] ?? '');
        $keys = is_array($spec['listKeys'] ?? null) ? $spec['listKeys'] : ['items'];
        $query = is_array($spec['listQuery'] ?? null) ? $spec['listQuery'] : [];

        $this->client->lastListTotal = null;
        $omit = ! empty($query['_omit_limit']);
        $maxPages = $omit ? 40 : 10000;
        $rows = $this->client->paginate($path, $keys, $query, $maxPages);
        $this->lastRemoteTotal = (int) ($this->client->lastListTotal ?? count($rows));

        return $rows;
    }

    /**
     * مقایسه فقط‌خواندنی omit-limit با صفحه‌بندی cursor+limit.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public function comparePagination(array $spec, int $pageLimit = 500): array
    {
        $path = (string) ($spec['path'] ?? '');
        $keys = is_array($spec['listKeys'] ?? null) ? $spec['listKeys'] : ['items'];
        $base = is_array($spec['listQuery'] ?? null) ? $spec['listQuery'] : [];
        $omitQuery = $base;
        $omitQuery['_omit_limit'] = true;
        unset($omitQuery['limit']);
        $pageQuery = $base;
        unset($pageQuery['_omit_limit']);
        $pageQuery['limit'] = max(1, $pageLimit);

        $this->client->lastListTotal = null;
        $omitRows = $this->client->paginate($path, $keys, $omitQuery, 40);
        $omitTotalHeader = $this->client->lastListTotal;
        $this->client->lastListTotal = null;
        $pageRows = $this->client->paginate($path, $keys, $pageQuery, 10000);
        $pageTotalHeader = $this->client->lastListTotal;

        $omitIds = $this->uniqueRemoteIds($spec, $omitRows);
        $pageIds = $this->uniqueRemoteIds($spec, $pageRows);
        $onlyOmit = array_values(array_diff($omitIds, $pageIds));
        $onlyPage = array_values(array_diff($pageIds, $omitIds));

        return [
            'path' => $path,
            'omitCount' => count($omitRows),
            'pagedCount' => count($pageRows),
            'omitUnique' => count($omitIds),
            'pagedUnique' => count($pageIds),
            'omitTotalHeader' => $omitTotalHeader,
            'pagedTotalHeader' => $pageTotalHeader,
            'onlyOmit' => $onlyOmit,
            'onlyPaged' => $onlyPage,
            'identical' => $onlyOmit === [] && $onlyPage === [] && count($omitIds) === count($pageIds),
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function uniqueRemoteIds(array $spec, array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = $this->remoteId($spec, $row);
            if ($id !== '') {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function newestFirst(array $spec, array $rows): array
    {
        if (($spec['family'] ?? '') === FazerCardsIds::GAMEKEY) {
            usort($rows, function (array $a, array $b): int {
                $byApp = ((int) ($b['appid'] ?? 0)) <=> ((int) ($a['appid'] ?? 0));
                if ($byApp !== 0) {
                    return $byApp;
                }

                return strcmp((string) ($b['game_id'] ?? ''), (string) ($a['game_id'] ?? ''));
            });
        } else {
            $rows = array_reverse($rows);
        }

        $ranked = [];
        foreach (array_values($rows) as $index => $row) {
            $row['listedRank'] = $index;
            $ranked[] = $row;
        }

        return $ranked;
    }

    /**
     * @return list<string>
     */
    private function existingRemoteIds(string $family): array
    {
        if ($family === '') {
            return [];
        }

        return Product::query()
            ->where('fulfillment_type', $family)
            ->whereNotNull('fazercards_category_id')
            ->where('fazercards_category_id', '!=', '')
            ->pluck('fazercards_category_id')
            ->map(fn ($id) => FazerCardsIds::rawCategory((string) $id))
            ->filter(fn ($id) => trim((string) $id) !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    private function stapleIds(array $spec): array
    {
        if (($spec['family'] ?? '') !== FazerCardsIds::TOPUP) {
            return [];
        }
        $ids = [];
        foreach (FazerCardsSeeds::beachhead() as $seed) {
            $id = trim((string) ($seed['categoryHint'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function markedRemoteIds(string $family): array
    {
        if ($family === '') {
            return [];
        }

        return Product::query()
            ->where('fulfillment_type', $family)
            ->where(function ($query) {
                $query->where('is_popular', true)
                    ->orWhere('requirements', 'like', '%shopPinned%');
            })
            ->pluck('fazercards_category_id')
            ->map(fn ($id) => FazerCardsIds::rawCategory((string) $id))
            ->filter(fn ($id) => trim((string) $id) !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $ids
     * @return list<array<string, mixed>>
     */
    private function rowsForIds(array $spec, array $rows, array $ids): array
    {
        $want = [];
        foreach ($ids as $id) {
            $want[$id] = true;
        }
        $out = [];
        foreach ($this->newestFirst($spec, $rows) as $row) {
            $id = $this->remoteId($spec, $row);
            if (isset($want[$id])) {
                $out[] = $row;
                unset($want[$id]);
            }
        }
        foreach ($ids as $id) {
            if (isset($want[$id])) {
                $out[] = ['id' => $id, 'category_id' => $id, 'game_id' => $id, 'name' => $id];
            }
        }

        return $out;
    }
}
