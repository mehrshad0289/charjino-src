<?php

namespace App\Services\Torob;

class TorobSplitRuntimeStore
{
    /**
     * @var array{enabled_product_ids: list<int>, enabled_group_slugs: list<string>}|null
     */
    private ?array $state = null;

    /**
     * @return list<int>
     */
    public function productIds(): array
    {
        return $this->state()['enabled_product_ids'];
    }

    /**
     * @return list<string>
     */
    public function groupSlugs(): array
    {
        return $this->state()['enabled_group_slugs'];
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $slugs
     */
    public function merge(array $ids = [], array $slugs = []): void
    {
        $state = $this->state();
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $state['enabled_product_ids'][$id] = $id;
            }
        }
        foreach ($slugs as $slug) {
            $slug = trim((string) $slug);
            if ($slug !== '') {
                $state['enabled_group_slugs'][$slug] = $slug;
            }
        }
        $this->persist($state);
    }

    /**
     * @param  list<int>  $ids
     */
    public function removeProductIds(array $ids): void
    {
        $state = $this->state();
        foreach ($ids as $id) {
            unset($state['enabled_product_ids'][(int) $id]);
        }
        $this->persist($state);
    }

    /**
     * @param  list<string>  $slugs
     */
    public function removeGroupSlugs(array $slugs): void
    {
        $state = $this->state();
        foreach ($slugs as $slug) {
            unset($state['enabled_group_slugs'][trim((string) $slug)]);
        }
        $this->persist($state);
    }

    public function clear(): void
    {
        $this->persist([
            'enabled_product_ids' => [],
            'enabled_group_slugs' => [],
        ]);
    }

    public function fingerprint(): string
    {
        $state = $this->state();
        $ids = array_values($state['enabled_product_ids']);
        sort($ids);
        $slugs = array_values($state['enabled_group_slugs']);
        sort($slugs);

        return hash('sha256', json_encode(['ids' => $ids, 'slugs' => $slugs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * @return array{enabled_product_ids: array<int, int>, enabled_group_slugs: array<string, string>}
     */
    private function state(): array
    {
        if ($this->state !== null) {
            return $this->state;
        }
        $decoded = TorobSplitJsonFile::read($this->path());
        $ids = [];
        foreach ($decoded['enabled_product_ids'] ?? [] as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $slugs = [];
        foreach ($decoded['enabled_group_slugs'] ?? [] as $slug) {
            $slug = trim((string) $slug);
            if ($slug !== '') {
                $slugs[$slug] = $slug;
            }
        }
        $this->state = [
            'enabled_product_ids' => $ids,
            'enabled_group_slugs' => $slugs,
        ];

        return $this->state;
    }

    /**
     * @param  array{enabled_product_ids: array<int, int>, enabled_group_slugs: array<string, string>}  $state
     */
    private function persist(array $state): void
    {
        $ids = array_values($state['enabled_product_ids']);
        sort($ids);
        $slugs = array_values($state['enabled_group_slugs']);
        sort($slugs);
        $this->state = [
            'enabled_product_ids' => array_combine($ids, $ids) ?: [],
            'enabled_group_slugs' => array_combine($slugs, $slugs) ?: [],
        ];
        TorobSplitJsonFile::write($this->path(), [
            'enabled_product_ids' => $ids,
            'enabled_group_slugs' => $slugs,
        ]);
    }

    private function path(): string
    {
        return (string) config('torob.split_by_sku.runtime_path', storage_path('app/torob/split-runtime.json'));
    }
}
