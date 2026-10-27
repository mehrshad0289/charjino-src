<?php

namespace App\Models;

use App\Modules\Catalog\CatalogMedia;
use Illuminate\Database\Eloquent\Model;

class BuyGuideMedia extends Model
{
    protected $table = 'buy_guide_media';

    protected $fillable = [
        'disk',
        'path',
        'original_path',
        'mime',
        'width',
        'height',
        'bytes',
        'alt',
        'variants',
        'kind',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
            'variants' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        $src = CatalogMedia::publicSrc('/storage/'.$this->path, '');
        $srcset = [];
        foreach (is_array($this->variants) ? $this->variants : [] as $width => $path) {
            $url = CatalogMedia::publicSrc('/storage/'.$path, '');
            if ($url !== '') {
                $srcset[] = $url.' '.$width.'w';
            }
        }

        return array_filter([
            'id' => $this->id,
            'src' => $src,
            'srcset' => $srcset !== [] ? implode(', ', $srcset) : null,
            'width' => $this->width,
            'height' => $this->height,
            'bytes' => $this->bytes,
            'alt' => $this->alt,
            'kind' => $this->kind,
            'mime' => $this->mime,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
