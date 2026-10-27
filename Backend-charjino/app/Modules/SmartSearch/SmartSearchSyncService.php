<?php

namespace App\Modules\SmartSearch;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\SearchTag;
use Meilisearch\Client;

final class SmartSearchSyncService
{
    public static bool $bulkQuiet = false;

    public function __construct(private readonly Client $client)
    {
    }

    public static function make(): self
    {
        return new self(MeiliClientFactory::makeForWrite());
    }

    public static function writesAreEnabled(): bool
    {
        if (self::$bulkQuiet) {
            return false;
        }

        return (bool) config('smart-search.index_writes');
    }

    /**
     * @param  list<int>  $productIds
     */
    public function syncProducts(array $productIds): void
    {
        if (! self::writesAreEnabled() || $productIds === []) {
            return;
        }
        $products = Product::query()->with(['catalogGroup', 'searchTags', 'skus'])->whereIn('id', $productIds)->get()->keyBy('id');
        $upsert = [];
        $delete = [];
        foreach ($productIds as $id) {
            $product = $products->get($id);
            if (! $product instanceof Product) {
                continue;
            }
            $doc = ProductSearchDocumentFactory::make($product);
            if ($doc === null) {
                $delete[] = SearchDocumentId::ofHandle((string) $product->handle);
            } else {
                $upsert[] = $doc;
            }
        }
        foreach (SearchIndexWriteRegistry::writeTargets()['products'] as $uid) {
            $index = $this->client->index($uid);
            if ($upsert !== []) {
                $task = $index->addDocuments($upsert, 'id');
                MeiliClientFactory::wait($this->client, $task['taskUid'] ?? 0, 180000);
            }
            if ($delete !== []) {
                $task = $index->deleteDocuments($delete);
                MeiliClientFactory::wait($this->client, $task['taskUid'] ?? 0, 60000);
            }
        }
    }

    /**
     * @param  list<string>  $handles
     */
    public function deleteProductHandles(array $handles): void
    {
        $ids = array_values(array_filter(array_map(
            fn (string $handle) => SearchDocumentId::ofHandle($handle),
            $handles,
        )));
        if ($ids === [] || ! self::writesAreEnabled()) {
            return;
        }
        foreach (SearchIndexWriteRegistry::writeTargets()['products'] as $uid) {
            $task = $this->client->index($uid)->deleteDocuments($ids);
            MeiliClientFactory::wait($this->client, $task['taskUid'] ?? 0, 60000);
        }
    }

    public function reindexAll(?callable $progress = null): void
    {
        $active = SearchIndexWriteRegistry::active();
        $this->rebuildProducts($active['products'], $progress);
        $this->rebuildBrands($active['brands']);
        $this->rebuildTaxonomies($active['taxonomies']);
    }

    public function rebuildProducts(string $uid, ?callable $progress = null): void
    {
        $index = $this->client->index($uid);
        $chunk = [];
        $count = 0;
        Product::query()
            ->with(['catalogGroup', 'searchTags', 'skus'])
            ->orderBy('id')
            ->chunkById(200, function ($products) use (&$chunk, &$count, $index, $progress) {
                foreach ($products as $product) {
                    $doc = ProductSearchDocumentFactory::make($product);
                    if ($doc === null) {
                        continue;
                    }
                    $chunk[] = $doc;
                    if (count($chunk) >= 200) {
                        $task = $index->addDocuments($chunk, 'id');
                        MeiliClientFactory::wait($this->client, $task['taskUid'] ?? 0, 180000);
                        $count += count($chunk);
                        $chunk = [];
                        if ($progress) {
                            $progress($count);
                        }
                    }
                }
            });
        if ($chunk !== []) {
            $task = $index->addDocuments($chunk, 'id');
            MeiliClientFactory::wait($this->client, $task['taskUid'] ?? 0, 180000);
            $count += count($chunk);
            if ($progress) {
                $progress($count);
            }
        }
    }

    public function rebuildBrands(string $uid): void
    {
        $docs = [];
        SearchTag::query()->where('kind', 'brand')->where('is_active', true)->with('catalogGroup')->each(function (SearchTag $tag) use (&$docs) {
            $doc = BrandSearchDocumentFactory::fromTag($tag);
            if ($doc !== null) {
                $docs[] = $doc;
            }
        });
        $index = $this->client->index($uid);
        $task = $index->deleteAllDocuments();
        MeiliClientFactory::wait($this->client, $task['taskUid'] ?? 0, 60000);
        if ($docs !== []) {
            $task = $index->addDocuments($docs, 'id');
            MeiliClientFactory::wait($this->client, $task['taskUid'] ?? 0, 120000);
        }
    }

    public function rebuildTaxonomies(string $uid): void
    {
        $docs = TaxonomySearchDocumentFactory::platforms();
        CatalogGroup::query()->orderBy('id')->each(function (CatalogGroup $group) use (&$docs) {
            $doc = TaxonomySearchDocumentFactory::fromGroup($group);
            if ($doc !== null) {
                $docs[] = $doc;
            }
        });
        $index = $this->client->index($uid);
        $task = $index->deleteAllDocuments();
        MeiliClientFactory::wait($this->client, $task['taskUid'] ?? 0, 60000);
        if ($docs !== []) {
            $task = $index->addDocuments($docs, 'id');
            MeiliClientFactory::wait($this->client, $task['taskUid'] ?? 0, 120000);
        }
    }

    /**
     * @param  list<int>  $productIds
     */
    public function catchUpProducts(array $productIds, string $uid): void
    {
        if ($productIds === []) {
            return;
        }
        $products = Product::query()->with(['catalogGroup', 'searchTags', 'skus'])->whereIn('id', $productIds)->get();
        $upsert = [];
        $delete = [];
        foreach ($products as $product) {
            $doc = ProductSearchDocumentFactory::make($product);
            if ($doc === null) {
                $delete[] = SearchDocumentId::ofHandle((string) $product->handle);
            } else {
                $upsert[] = $doc;
            }
        }
        $index = $this->client->index($uid);
        if ($upsert !== []) {
            $task = $index->addDocuments($upsert, 'id');
            MeiliClientFactory::wait($this->client, $task['taskUid'] ?? 0, 180000);
        }
        if ($delete !== []) {
            $task = $index->deleteDocuments($delete);
            MeiliClientFactory::wait($this->client, $task['taskUid'] ?? 0, 60000);
        }
    }
}
