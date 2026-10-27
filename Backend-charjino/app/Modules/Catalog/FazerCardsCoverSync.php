<?php

namespace App\Modules\Catalog;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Modules\Supplier\FazerCards\FazerCardsClient;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsMedia;

final class FazerCardsCoverSync
{
    public function __construct(private readonly FazerCardsClient $client) {}

    /**
     * @return array{local: int, remote: int}
     */
    public function refresh(): array
    {
        $local = $this->applyLocal();
        $remote = $this->client->configured() ? $this->applyRemote() : 0;
        $this->refreshGroupCovers();

        return ['local' => $local, 'remote' => $remote];
    }

    public function applyLocal(): int
    {
        $updated = 0;
        Product::query()
            ->where('fulfillment_type', FazerCardsIds::STEAM_GIFT)
            ->orderBy('id')
            ->each(function (Product $product) use (&$updated): void {
                if ($this->locked((string) ($product->image_url ?: $product->thumbnail))) {
                    return;
                }
                $current = trim((string) ($product->image_url ?: $product->thumbnail));
                if ($current !== '' && ! str_contains($current, 'pubg-mobile.svg') && ! FazerCardsMedia::isLegacySteamHeader($current)) {
                    return;
                }
                $url = FazerCardsMedia::steamHeader(FazerCardsIds::rawCategory($product->fazercards_category_id));
                if ($url === '' || $url === $product->thumbnail) {
                    return;
                }
                $product->thumbnail = $url;
                $product->image_url = $url;
                $product->save();
                $updated++;
            });

        $updated += $this->setFamilyCover(FazerCardsIds::STEAM_TOPUP, '/image/catalog/steam.svg');
        $updated += $this->setFamilyCover(FazerCardsIds::TELEGRAM_STARS, '/image/catalog/telegram.svg');
        $updated += $this->setFamilyCover(FazerCardsIds::TELEGRAM_PREMIUM, '/image/catalog/telegram.svg');

        return $updated;
    }

    public function applyRemote(): int
    {
        $updated = 0;
        $updated += $this->fromList($this->client->allTopups(), FazerCardsIds::TOPUP, ['category_id', 'id']);
        $updated += $this->fromList($this->client->allGiftcards(), FazerCardsIds::GIFTCARD, ['category_id', 'id']);
        $updated += $this->fromList($this->client->allGamekeys(), FazerCardsIds::GAMEKEY, ['game_id', 'id']);
        $updated += $this->fromList($this->client->allManualServices(), FazerCardsIds::MANUAL, ['id', 'manual_service_id', 'category_id']);

        return $updated;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $idKeys
     */
    private function fromList(array $rows, string $family, array $idKeys): int
    {
        $products = Product::query()
            ->where('fulfillment_type', $family)
            ->get()
            ->keyBy('fazercards_category_id');
        $updated = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $remoteId = $this->idOf($row, $idKeys);
            if ($remoteId === '') {
                continue;
            }
            $product = $products->get(FazerCardsIds::productKey($family, $remoteId));
            if (! $product || $this->locked((string) ($product->image_url ?: $product->thumbnail))) {
                continue;
            }
            $cover = FazerCardsMedia::absolute($this->idOf($row, ['imageurl', 'image_url', 'image', 'icon', 'thumbnail', 'cover']));
            if ($cover === '') {
                $cover = FazerCardsMedia::steamHeader($this->idOf($row, ['appid', 'app_id']));
            }
            if ($cover === '' || str_contains($cover, 'pubg-mobile.svg')) {
                continue;
            }
            $dirty = false;
            if ($product->thumbnail !== $cover || $product->image_url !== $cover) {
                $product->thumbnail = $cover;
                $product->image_url = $cover;
                $dirty = true;
            }
            $appid = $this->idOf($row, ['appid', 'app_id']);
            if ($family === FazerCardsIds::GAMEKEY && $appid !== '' && (int) $appid > 0) {
                $requirements = is_array($product->requirements) ? $product->requirements : [];
                if ((int) ($requirements['appid'] ?? 0) !== (int) $appid) {
                    $requirements['appid'] = (int) $appid;
                    $product->requirements = $requirements;
                    $dirty = true;
                }
            }
            if ($dirty) {
                $product->save();
                $updated++;
            }
        }

        return $updated;
    }

    private function setFamilyCover(string $family, string $cover): int
    {
        $updated = 0;
        Product::query()
            ->where('fulfillment_type', $family)
            ->orderBy('id')
            ->each(function (Product $product) use ($cover, &$updated): void {
                if ($this->locked((string) ($product->image_url ?: $product->thumbnail))) {
                    return;
                }
                if ($product->thumbnail === $cover && $product->image_url === $cover) {
                    return;
                }
                $product->thumbnail = $cover;
                $product->image_url = $cover;
                $product->save();
                $updated++;
            });

        return $updated;
    }

    private function refreshGroupCovers(): void
    {
        CatalogGroup::query()->orderBy('id')->each(function (CatalogGroup $group): void {
            if ($this->locked((string) $group->image_url)) {
                return;
            }
            $product = Product::query()
                ->where('catalog_group_id', $group->id)
                ->where(function ($query): void {
                    $query->where('thumbnail', 'like', 'http%')
                        ->orWhere('image_url', 'like', 'http%')
                        ->orWhere('thumbnail', 'like', '/image/catalog/%');
                })
                ->where('thumbnail', 'not like', '%pubg-mobile.svg%')
                ->orderBy('id')
                ->first();
            if (! $product) {
                return;
            }
            $cover = $product->displayImage();
            if ($cover === '' || str_contains($cover, 'pubg-mobile.svg') || $group->image_url === $cover) {
                return;
            }
            $group->image_url = $cover;
            $group->save();
        });
    }

    private function locked(string $src): bool
    {
        $src = trim($src);
        if ($src === '' || str_starts_with($src, '/image/')) {
            return false;
        }
        if (str_starts_with($src, '/storage/')) {
            return true;
        }
        $path = parse_url($src, PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/storage/');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function idOf(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }
            if (is_scalar($row[$key])) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }
}
