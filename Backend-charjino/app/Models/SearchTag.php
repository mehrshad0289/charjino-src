<?php

namespace App\Models;

use App\Modules\Catalog\SearchQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class SearchTag extends Model
{
    use Concerns\SearchIndexable;

    protected $fillable = [
        'slug',
        'label',
        'aliases',
        'aliases_text',
        'attach_pattern',
        'kind',
        'catalog_group_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected function searchIndexName(): string
    {
        return 'brands';
    }

    public function catalogGroup(): BelongsTo
    {
        return $this->belongsTo(CatalogGroup::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_search_tag')->withTimestamps();
    }

    /**
     * @param  list<mixed>  $rawLabels
     */
    public static function syncLabels(Product $product, array $rawLabels): void
    {
        $ids = [];
        $seen = [];
        foreach ($rawLabels as $raw) {
            if (! is_scalar($raw)) {
                continue;
            }
            $label = SearchQuery::normalize((string) $raw);
            if (mb_strlen($label) < 2) {
                continue;
            }
            $key = mb_strtolower($label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $ids[] = self::findOrCreateFromLabel($label)->id;
        }
        $product->searchTags()->sync($ids);
        if (\App\Modules\SmartSearch\SmartSearchSyncService::writesAreEnabled()) {
            \App\Jobs\SmartSearch\SyncProductSearchDocument::dispatch((int) $product->id, (string) $product->handle);
        }
    }

    public static function findOrCreateFromLabel(string $label): self
    {
        $normalized = SearchQuery::normalize($label);
        $existing = self::query()
            ->whereRaw('LOWER(label) = ?', [mb_strtolower($normalized)])
            ->first();
        if ($existing) {
            return $existing;
        }

        $slug = Str::slug($normalized);
        if ($slug === '') {
            $slug = 'tag-'.substr(sha1(mb_strtolower($normalized)), 0, 12);
        }
        $unique = $slug;
        $n = 2;
        while (self::query()->where('slug', $unique)->exists()) {
            $unique = $slug.'-'.$n;
            $n++;
        }

        return self::query()->create([
            'slug' => $unique,
            'label' => $normalized,
            'aliases' => [$normalized],
            'aliases_text' => $normalized,
        ]);
    }

    public function haystack(): string
    {
        return SearchQuery::normalize(trim($this->label.' '.$this->aliases_text));
    }
}
