<?php

namespace App\Modules\Supplier\FazerCards;

use App\Exceptions\ApiException;

final class FazerCardsSteamGiftCatalog
{
    public const CHUNK = 500;

    public const TTL_SECONDS = 10800;

    public function __construct(private readonly FazerCardsClient $client) {}

    public function invalidate(): void
    {
        $dir = $this->dir();
        foreach (glob($dir.DIRECTORY_SEPARATOR.'c*.json') ?: [] as $old) {
            @unlink($old);
        }
        if (is_file($this->metaPath())) {
            @unlink($this->metaPath());
        }
    }

    public function total(): int
    {
        $meta = $this->meta();
        if ($this->fresh($meta)) {
            return (int) $meta['total'];
        }

        return $this->refresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function at(int $index): ?array
    {
        $this->total();
        if ($index < 0) {
            return null;
        }
        $chunk = intdiv($index, self::CHUNK);
        $local = $index % self::CHUNK;
        $path = $this->chunkPath($chunk);
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
    public function search(string $needle, int $limit = 20): array
    {
        $this->total();
        $limit = max(1, min(40, $limit));
        $query = mb_strtolower(trim($needle));
        if ($query === '') {
            return array_slice($this->newestRows($limit), 0, $limit);
        }
        if (preg_match('/^\d+$/', $query) === 1) {
            $row = $this->findApp((int) $query);

            return $row ? [$row] : [];
        }
        $hits = [];
        foreach ($this->eachRow() as $row) {
            $name = mb_strtolower(trim((string) ($row['name'] ?? '')));
            $appid = (string) ((int) ($row['appid'] ?? 0));
            if ($name === '' && $appid === '0') {
                continue;
            }
            if (! str_contains($name, $query) && ! str_contains($appid, $query)) {
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
     * @return list<int>
     */
    public function newestAppIds(int $limit): array
    {
        $this->total();
        $limit = max(1, min(50000, $limit));
        $ids = [];
        foreach ($this->eachRow() as $row) {
            $appid = (int) ($row['appid'] ?? 0);
            if ($appid > 0) {
                $ids[] = $appid;
            }
        }
        rsort($ids, SORT_NUMERIC);

        return array_values(array_slice(array_unique($ids), 0, $limit));
    }

    /**
     * @param  list<int>  $appIds
     * @return list<array<string, mixed>>
     */
    public function rowsForAppIds(array $appIds): array
    {
        $this->total();
        $want = [];
        foreach ($appIds as $appid) {
            $id = (int) $appid;
            if ($id > 0) {
                $want[$id] = true;
            }
        }
        if ($want === []) {
            return [];
        }
        $rows = [];
        foreach ($this->eachRow() as $row) {
            $appid = (int) ($row['appid'] ?? 0);
            if (isset($want[$appid])) {
                $rows[] = $row;
                unset($want[$appid]);
                if ($want === []) {
                    break;
                }
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findApp(int $appId): ?array
    {
        $rows = $this->rowsForAppIds([$appId]);

        return $rows[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function newestRows(int $limit): array
    {
        return $this->rowsForAppIds($this->newestAppIds($limit));
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function eachRow(): \Generator
    {
        $total = $this->total();
        $chunks = $total > 0 ? (int) ceil($total / self::CHUNK) : 0;
        for ($chunk = 0; $chunk < $chunks; $chunk++) {
            $path = $this->chunkPath($chunk);
            if (! is_file($path)) {
                continue;
            }
            $rows = json_decode((string) file_get_contents($path), true);
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (is_array($row)) {
                    yield $row;
                }
            }
        }
    }

    private function refresh(): int
    {
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }
        $result = $this->client->fetch('steam-gifts/games', 'GET', []);
        $games = FazerCardsPayload::listFrom($result['data'], ['games', 'items']);
        $dir = $this->dir();
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new ApiException('ذخیرهٔ فهرست گیفت استیم ممکن نشد.', 503, 'SUPPLIER_UNAVAILABLE');
        }
        foreach (glob($dir.DIRECTORY_SEPARATOR.'c*.json') ?: [] as $old) {
            @unlink($old);
        }
        foreach (array_chunk($games, self::CHUNK) as $i => $chunk) {
            $this->writeOwnedFile($this->chunkPath($i), json_encode(array_values($chunk), JSON_UNESCAPED_UNICODE) ?: '[]');
        }
        $total = count($games);
        $this->writeOwnedFile($this->metaPath(), json_encode([
            'total' => $total,
            'fetchedAt' => time(),
            'chunkSize' => self::CHUNK,
        ], JSON_UNESCAPED_UNICODE) ?: '{}');

        return $total;
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(): array
    {
        $path = $this->metaPath();
        if (! is_file($path)) {
            return [];
        }
        $row = json_decode((string) file_get_contents($path), true);

        return is_array($row) ? $row : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function fresh(array $meta): bool
    {
        if (app()->environment('testing')) {
            return false;
        }
        $total = (int) ($meta['total'] ?? 0);
        $fetched = (int) ($meta['fetchedAt'] ?? 0);

        return $total > 0 && $fetched > 0 && (time() - $fetched) < self::TTL_SECONDS && is_file($this->chunkPath(0));
    }

    private function writeOwnedFile(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new ApiException('ذخیرهٔ فهرست گیفت استیم ممکن نشد.', 503, 'SUPPLIER_UNAVAILABLE');
        }
        if (file_put_contents($path, $contents) === false) {
            throw new ApiException('نوشتن فهرست گیفت استیم ممکن نشد (دسترسی پوشه).', 503, 'SUPPLIER_UNAVAILABLE');
        }
        @chmod($path, 0664);
    }

    private function dir(): string
    {
        if (app()->environment('testing')) {
            return storage_path('framework/testing/fazer/steam-gifts');
        }

        return storage_path('app/fazer/steam-gifts');
    }

    private function metaPath(): string
    {
        return $this->dir().DIRECTORY_SEPARATOR.'meta.json';
    }

    private function chunkPath(int $chunk): string
    {
        return $this->dir().DIRECTORY_SEPARATOR.'c'.$chunk.'.json';
    }
}
