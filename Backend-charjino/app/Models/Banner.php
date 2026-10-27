<?php

namespace App\Models;

use App\Models\Concerns\FlushesStorefrontHomeCache;
use App\Modules\Catalog\MediaType;
use App\Modules\Catalog\CatalogMedia;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use FlushesStorefrontHomeCache;

    protected $fillable = [
        'src',
        'media_type',
        'title',
        'href',
        'description',
        'sort_order',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'visible' => 'boolean',
        ];
    }

    public function resolvedMediaType(): string
    {
        $type = strtolower(trim((string) $this->media_type));
        if (in_array($type, ['image', 'gif', 'video'], true)) {
            return $type;
        }

        return MediaType::fromSrc((string) $this->src);
    }

    public function storefrontSrc(): string
    {
        $src = CatalogMedia::publicSrc($this->src, '');
        if (! str_starts_with($src, '/storage/')) {
            return $src;
        }
        $stamp = $this->updated_at?->getTimestamp() ?: time();

        return $src.(str_contains($src, '?') ? '&' : '?').'v='.$stamp;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'src' => $this->storefrontSrc(),
            'mediaType' => $this->resolvedMediaType(),
            'title' => $this->title,
            'href' => $this->href,
            'description' => $this->description,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(): array
    {
        return $this->toApi() + [
            'visible' => (bool) $this->getAttribute('visible'),
            'sortOrder' => (int) $this->sort_order,
        ];
    }
}
