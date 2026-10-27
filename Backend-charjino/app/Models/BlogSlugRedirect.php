<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogSlugRedirect extends Model
{
    public const TYPE_POST = 'post';

    public const TYPE_CATEGORY = 'category';

    public const TYPE_TAG = 'tag';

    protected $fillable = [
        'type',
        'from_slug',
        'to_slug',
    ];

    public static function remember(string $type, string $from, string $to): void
    {
        $from = trim($from);
        $to = trim($to);
        if ($from === '' || $to === '' || $from === $to) {
            return;
        }
        static::query()->where('type', $type)->where('from_slug', $to)->delete();
        static::query()->updateOrCreate(
            ['type' => $type, 'from_slug' => $from],
            ['to_slug' => $to],
        );
        static::query()
            ->where('type', $type)
            ->where('to_slug', $from)
            ->update(['to_slug' => $to]);
    }

    public static function resolve(string $type, string $from): ?string
    {
        $seen = [];
        $current = trim($from);
        while ($current !== '') {
            $row = static::query()->where('type', $type)->where('from_slug', $current)->first();
            if (! $row) {
                break;
            }
            if (isset($seen[$current])) {
                break;
            }
            $seen[$current] = true;
            $current = (string) $row->to_slug;
        }

        return $current !== '' && $current !== $from ? $current : null;
    }
}
