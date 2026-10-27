<?php

namespace App\Modules\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Support\Str;

final class BlogSlug
{
    public static function make(string $source, string $fallback = 'post'): string
    {
        $trimmed = trim($source);
        if ($trimmed === '') {
            return $fallback;
        }
        $slug = Str::of($trimmed)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s_-]+/u', '')
            ->replaceMatches('/[\s_]+/u', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->substr(0, 80)
            ->toString();

        return $slug !== '' ? $slug : $fallback;
    }

    public static function latinize(string $source, string $fallback = 'post'): string
    {
        $map = [
            'آ' => 'a', 'ا' => 'a', 'أ' => 'a', 'إ' => 'e', 'ء' => '',
            'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's',
            'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh',
            'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z', 'ژ' => 'zh',
            'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'z',
            'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh',
            'ف' => 'f', 'ق' => 'gh', 'ک' => 'k', 'ك' => 'k', 'گ' => 'g',
            'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'و' => 'v', 'ه' => 'h',
            'ی' => 'y', 'ي' => 'y', 'ئ' => 'y', 'ة' => 'h',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ];

        return self::make(strtr($source, $map), $fallback);
    }

    public static function uniquePost(string $source, ?int $ignoreId = null): string
    {
        return self::unique($source, 'post', function (string $slug) use ($ignoreId): bool {
            return BlogPost::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query, $id) => $query->where('id', '!=', $id))
                ->exists();
        });
    }

    public static function uniqueCategory(string $source, ?int $ignoreId = null): string
    {
        return self::unique($source, 'category', function (string $slug) use ($ignoreId): bool {
            return BlogCategory::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query, $id) => $query->where('id', '!=', $id))
                ->exists();
        });
    }

    public static function uniqueTag(string $source, ?int $ignoreId = null): string
    {
        return self::unique($source, 'tag', function (string $slug) use ($ignoreId): bool {
            return BlogTag::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query, $id) => $query->where('id', '!=', $id))
                ->exists();
        });
    }

    public static function uniqueAuthor(string $source, ?string $ignoreId = null): string
    {
        return self::unique($source, 'author', function (string $slug) use ($ignoreId): bool {
            return User::query()
                ->where('blog_slug', $slug)
                ->when($ignoreId, fn ($query, $id) => $query->where('id', '!=', $id))
                ->exists();
        });
    }

    public static function postTaken(string $slug, ?int $ignoreId = null): bool
    {
        return BlogPost::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->exists();
    }

    /**
     * @param  callable(string): bool  $taken
     */
    private static function unique(string $source, string $fallback, callable $taken): string
    {
        $base = self::make($source, $fallback);
        $slug = $base;
        $n = 2;
        while ($taken($slug)) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }
}
