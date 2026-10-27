<?php

namespace App\Modules\SmartSearch;

use App\Models\SearchSynonym;
use Meilisearch\Client;

final class SmartSearchIndexManager
{
    public function __construct(private readonly Client $client)
    {
    }

    public static function make(): self
    {
        return new self(MeiliClientFactory::makeForWrite());
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function ensureIndex(string $uid, array $settings, ?array $synonyms = null): void
    {
        try {
            $this->client->getIndex($uid);
            $exists = true;
        } catch (\Throwable) {
            $exists = false;
        }
        if (! $exists) {
            $task = $this->client->createIndex($uid, ['primaryKey' => 'id']);
            MeiliClientFactory::wait($this->client, $task['taskUid'] ?? 0);
        }
        $index = $this->client->index($uid);
        $task = $index->updateSettings($settings);
        MeiliClientFactory::wait($this->client, $task['taskUid'] ?? $task['uid'] ?? 0);
        if ($synonyms !== null) {
            $task = $index->updateSynonyms($synonyms);
            MeiliClientFactory::wait($this->client, $task['taskUid'] ?? $task['uid'] ?? 0);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public static function synonymMap(): array
    {
        $map = [];
        $rows = SearchSynonym::query()->where('is_active', true)->get();
        foreach ($rows as $row) {
            $term = PersianTextNormalizer::normalize((string) $row->term);
            $syns = [];
            foreach ((array) $row->synonyms as $syn) {
                $n = PersianTextNormalizer::normalize((string) $syn);
                if ($n !== '' && $n !== $term) {
                    $syns[$n] = $n;
                }
            }
            if ($term === '' || $syns === []) {
                continue;
            }
            $all = array_values($syns);
            $map[$term] = array_values(array_unique(array_merge($map[$term] ?? [], $all)));
            foreach ($all as $syn) {
                $map[$syn] = array_values(array_unique(array_merge($map[$syn] ?? [], [$term])));
            }
        }

        return $map;
    }

    public function configureAll(): void
    {
        $active = SearchIndexWriteRegistry::active();
        $syn = self::synonymMap();
        $this->ensureIndex($active['products'], SearchIndexSettings::products(), $syn);
        $this->ensureIndex($active['brands'], SearchIndexSettings::brands(), $syn);
        $this->ensureIndex($active['taxonomies'], SearchIndexSettings::taxonomies(), $syn);
    }
}
