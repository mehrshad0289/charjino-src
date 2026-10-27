<?php

namespace App\Services\Torob;

class TorobSplitCanonicalStore
{
    /**
     * @var array<int, int>|null
     */
    private ?array $map = null;

    public function get(int $productId): ?int
    {
        $skuId = $this->all()[$productId] ?? null;

        return $skuId !== null && $skuId > 0 ? $skuId : null;
    }

    /**
     * @return array<int, int>
     */
    public function all(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }
        $decoded = TorobSplitJsonFile::read($this->path());
        $map = [];
        foreach ($decoded as $productId => $skuId) {
            $pid = (int) $productId;
            $sid = (int) $skuId;
            if ($pid > 0 && $sid > 0) {
                $map[$pid] = $sid;
            }
        }
        $this->map = $map;

        return $this->map;
    }

    public function put(int $productId, int $skuId): void
    {
        if ($productId < 1 || $skuId < 1) {
            return;
        }
        $map = $this->all();
        $map[$productId] = $skuId;
        $this->replace($map);
    }

    /**
     * @param  array<int, int>  $pairs
     */
    public function putMany(array $pairs): void
    {
        $map = $this->all();
        foreach ($pairs as $productId => $skuId) {
            $pid = (int) $productId;
            $sid = (int) $skuId;
            if ($pid > 0 && $sid > 0) {
                $map[$pid] = $sid;
            }
        }
        $this->replace($map);
    }

    public function forget(int $productId): void
    {
        $this->forgetMany([$productId]);
    }

    /**
     * @param  list<int>  $productIds
     */
    public function forgetMany(array $productIds): int
    {
        $map = $this->all();
        $removed = 0;
        foreach ($productIds as $productId) {
            $pid = (int) $productId;
            if ($pid > 0 && isset($map[$pid])) {
                unset($map[$pid]);
                $removed++;
            }
        }
        if ($removed > 0) {
            $this->replace($map);
        }

        return $removed;
    }

    public function fingerprint(): string
    {
        $map = $this->all();
        ksort($map);

        return hash('sha256', json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * @param  array<int, int>  $map
     */
    private function replace(array $map): void
    {
        ksort($map);
        $this->map = $map;
        TorobSplitJsonFile::write($this->path(), $map);
    }

    private function path(): string
    {
        return (string) config('torob.split_by_sku.canonical_path', storage_path('app/torob/split-canonical.json'));
    }
}
