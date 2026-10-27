<?php

namespace App\Models;

use App\Modules\Catalog\StorefrontHomeCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $cached = Cache::get('setting:'.$key);
        if (is_string($cached)) {
            return $cached;
        }

        $row = static::query()->find($key);
        $value = $row?->value ?? $default;
        if (is_string($value)) {
            Cache::put('setting:'.$key, $value, 60);
        }

        return $value;
    }

    public static function putValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        Cache::forget('setting:'.$key);
        StorefrontHomeCache::forgetIfHomeSetting($key);
    }
}
