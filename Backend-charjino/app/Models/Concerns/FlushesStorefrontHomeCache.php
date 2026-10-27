<?php

namespace App\Models\Concerns;

use App\Modules\Catalog\StorefrontHomeCache;

trait FlushesStorefrontHomeCache
{
    protected static function bootFlushesStorefrontHomeCache(): void
    {
        static::saved(static fn () => StorefrontHomeCache::forget());
        static::deleted(static fn () => StorefrontHomeCache::forget());
    }
}
