<?php

namespace App\Modules\Supplier\FazerCards;

use App\Models\Product;

final class FazerCardsSteamGiftShop
{
    public const SHOP_MAX_CEILING = 50000;

    public const QUEUE_CHUNK = 500;

    private string $queueName = 'full';

    /**
     * بازی‌های پرمشتری که appid قدیمی دارند و در «جدیدترین‌ها» نمی‌آیند.
     *
     * @var list<int>
     */
    public const STAPLE_APP_IDS = [
        730,
        570,
        440,
        252490,
        1172470,
        1091500,
        271590,
        1245620,
        1085660,
        1938090,
        1623730,
        990080,
        1145360,
        1174180,
        892970,
        413150,
    ];

    public function __construct(private readonly FazerCardsSteamGiftCatalog $catalog) {}

    public function shopMax(): int
    {
        return max(1, min(self::SHOP_MAX_CEILING, (int) config('charjino.supplier.fazercards_steam_gifts_shop_max', self::SHOP_MAX_CEILING)));
    }

    /**
     * سقف همگام: منتخب فروشگاه + کمی محصول پین‌شدهٔ دستی.
     */
    public function syncCap(): int
    {
        return min(self::SHOP_MAX_CEILING, $this->shopMax() + 40);
    }

    /**
     * @return list<int>
     */
    public function keepAppIds(): array
    {
        $ids = array_merge(
            self::STAPLE_APP_IDS,
            $this->markedAppIds(),
            $this->catalog->newestAppIds($this->shopMax()),
        );

        return $this->uniqueAppIds($ids);
    }

    /**
     * صف همگام را روی دیسک می‌نویسد (نه در کش PHP) تا ۵۰ هزار ردیف حافظه را پر نکند.
     */
    public function prepareWorklist(): int
    {
        $this->queueName = 'full';
        $rows = $this->worklistRows();
        usort($rows, fn (array $a, array $b) => ((int) ($b['appid'] ?? 0)) <=> ((int) ($a['appid'] ?? 0)));
        $this->writeQueue($rows);

        return count($rows);
    }

    /**
     * صف همگام سبک: فقط بازی‌های visible فعلی. منطق keep/shop_max عوض نمی‌شود.
     */
    public function prepareVisibleWorklist(): int
    {
        $this->queueName = 'visible';
        $this->catalog->total();
        $ids = $this->visibleAppIds();
        $rows = $this->catalog->rowsForAppIds($ids);
        $have = [];
        foreach ($rows as $row) {
            $have[(int) ($row['appid'] ?? 0)] = true;
        }
        foreach ($ids as $id) {
            if ($id > 0 && ! isset($have[$id])) {
                $rows[] = ['appid' => $id, 'name' => ''];
            }
        }
        usort($rows, fn (array $a, array $b) => ((int) ($b['appid'] ?? 0)) <=> ((int) ($a['appid'] ?? 0)));
        $this->writeQueue($rows);

        return count($rows);
    }

    public function useQueue(string $name): void
    {
        $this->queueName = $name === 'visible' ? 'visible' : 'full';
    }

    public function queueTotal(): int
    {
        $meta = $this->queueMeta();

        return (int) ($meta['total'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function queueAt(int $index): ?array
    {
        if ($index < 0) {
            return null;
        }
        $chunk = intdiv($index, self::QUEUE_CHUNK);
        $local = $index % self::QUEUE_CHUNK;
        $path = $this->queueChunkPath($chunk);
        if (! is_file($path)) {
            return null;
        }
        $rows = json_decode((string) file_get_contents($path), true);
        if (! is_array($rows) || ! is_array($rows[$local] ?? null)) {
            return null;
        }

        return $rows[$local];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function worklistRows(): array
    {
        return $this->catalog->rowsForAppIds($this->worklistAppIds());
    }

    /**
     * @return array{keep: int, shown: int, hidden: int, visible: int}
     */
    public function curate(): array
    {
        $keep = array_fill_keys($this->keepAppIds(), true);
        $shown = 0;
        $hidden = 0;
        Product::query()
            ->where('fulfillment_type', FazerCardsIds::STEAM_GIFT)
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($keep, &$shown, &$hidden): void {
                foreach ($products as $product) {
                    $appid = (int) FazerCardsIds::rawCategory((string) $product->fazercards_category_id);
                    $want = $appid > 0 && isset($keep[$appid]);
                    $visible = (bool) $product->getAttribute('visible');
                    if ($want && ! $visible) {
                        $product->visible = true;
                        $product->status = 'active';
                        $product->save();
                        $shown++;
                    } elseif (! $want && $visible) {
                        $product->visible = false;
                        $product->save();
                        $hidden++;
                    }
                }
            });

        return [
            'keep' => count($keep),
            'shown' => $shown,
            'hidden' => $hidden,
            'visible' => Product::query()
                ->where('fulfillment_type', FazerCardsIds::STEAM_GIFT)
                ->where('visible', true)
                ->count(),
        ];
    }

    /**
     * @return list<int>
     */
    public function worklistAppIds(): array
    {
        $cap = $this->syncCap();
        $keep = $this->keepAppIds();
        $visible = $this->visibleAppIds();
        $selected = [];
        $seen = [];
        $add = function (int $appid) use (&$selected, &$seen, $cap): void {
            if ($appid <= 0 || isset($seen[$appid]) || count($selected) >= $cap) {
                return;
            }
            $seen[$appid] = true;
            $selected[] = $appid;
        };
        if (count($visible) <= $cap) {
            foreach ($visible as $appid) {
                $add($appid);
            }
            foreach ($keep as $appid) {
                $add($appid);
            }

            return $selected;
        }

        $keepSet = array_fill_keys($keep, true);
        foreach ($visible as $appid) {
            if (isset($keepSet[$appid])) {
                $add($appid);
            }
        }
        foreach ($keep as $appid) {
            $add($appid);
        }

        return $selected;
    }

    /**
     * @return list<int>
     */
    private function visibleAppIds(): array
    {
        return $this->uniqueAppIds(
            Product::query()
                ->where('fulfillment_type', FazerCardsIds::STEAM_GIFT)
                ->where('visible', true)
                ->pluck('fazercards_category_id')
                ->map(fn ($id) => (int) FazerCardsIds::rawCategory((string) $id))
                ->all()
        );
    }

    /**
     * @return list<int>
     */
    private function markedAppIds(): array
    {
        return $this->uniqueAppIds(
            Product::query()
                ->where('fulfillment_type', FazerCardsIds::STEAM_GIFT)
                ->where(function ($query) {
                    $query->where('is_popular', true)
                        ->orWhere('requirements', 'like', '%shopPinned%');
                })
                ->pluck('fazercards_category_id')
                ->map(fn ($id) => (int) FazerCardsIds::rawCategory((string) $id))
                ->all()
        );
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private function uniqueAppIds(array $ids): array
    {
        $out = [];
        $seen = [];
        foreach ($ids as $id) {
            $appid = (int) $id;
            if ($appid > 0 && ! isset($seen[$appid])) {
                $seen[$appid] = true;
                $out[] = $appid;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeQueue(array $rows): void
    {
        $dir = $this->queueDir();
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return;
        }
        foreach (glob($dir.DIRECTORY_SEPARATOR.'q*.json') ?: [] as $old) {
            @unlink($old);
        }
        foreach (array_chunk($rows, self::QUEUE_CHUNK) as $i => $chunk) {
            file_put_contents($this->queueChunkPath($i), json_encode(array_values($chunk), JSON_UNESCAPED_UNICODE));
        }
        file_put_contents($this->queueMetaPath(), json_encode([
            'total' => count($rows),
            'chunkSize' => self::QUEUE_CHUNK,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private function queueMeta(): array
    {
        $path = $this->queueMetaPath();
        if (! is_file($path)) {
            return [];
        }
        $row = json_decode((string) file_get_contents($path), true);

        return is_array($row) ? $row : [];
    }

    private function queueDir(): string
    {
        $base = $this->catalogDir().DIRECTORY_SEPARATOR.'shop-queue';

        return $this->queueName === 'visible' ? $base.'-visible' : $base;
    }

    private function catalogDir(): string
    {
        if (app()->environment('testing')) {
            return storage_path('framework/testing/fazer/steam-gifts');
        }

        return storage_path('app/fazer/steam-gifts');
    }

    private function queueMetaPath(): string
    {
        return $this->queueDir().DIRECTORY_SEPARATOR.'meta.json';
    }

    private function queueChunkPath(int $chunk): string
    {
        return $this->queueDir().DIRECTORY_SEPARATOR.'q'.$chunk.'.json';
    }
}
