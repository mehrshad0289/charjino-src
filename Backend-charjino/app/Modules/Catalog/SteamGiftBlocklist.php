<?php

namespace App\Modules\Catalog;

use App\Models\Product;
use App\Models\Setting;
use Throwable;

final class SteamGiftBlocklist
{
    public const SETTING_KEY = 'steam_gift_blocked_words';

    public const DEFAULT_TEXT = "femboy\nfuta\nnsfw\nhentai\nporn\nerotic\nincest\nloli\nshota\nnude\nnudity\nsex game\nadult only\nxxx\n5041000\n5040990";

    public static function defaultText(): string
    {
        return self::DEFAULT_TEXT;
    }

    public static function text(): string
    {
        $stored = self::storedText();

        return $stored !== '' ? $stored : self::DEFAULT_TEXT;
    }

    /**
     * @return list<string>
     */
    public static function words(): array
    {
        return self::parse(self::text());
    }

    /**
     * @return list<string>
     */
    public static function parse(string $raw): array
    {
        $words = [];
        $seen = [];
        foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $part) {
            $word = mb_strtolower(trim($part));
            $word = trim($word, " \t.-_");
            if ($word === '' || isset($seen[$word])) {
                continue;
            }
            $seen[$word] = true;
            $words[] = $word;
        }

        return $words;
    }

    public static function matches(string $haystack): bool
    {
        $hay = mb_strtolower(trim($haystack));
        if ($hay === '') {
            return false;
        }
        foreach (self::words() as $word) {
            $quoted = preg_quote($word, '/');
            if (preg_match('/(^|[^a-z0-9])'.$quoted.'([^a-z0-9]|$)/u', $hay) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function putText(string $raw): string
    {
        $parsed = self::parse($raw);
        $text = $parsed === [] ? self::DEFAULT_TEXT : implode("\n", $parsed);
        Setting::putValue(self::SETTING_KEY, $text);
        self::applyExisting();

        return $text;
    }

    public static function applyExisting(): int
    {
        $count = 0;
        Product::query()
            ->where('handle', 'like', 'steam-gift-%')
            ->orderBy('id')
            ->each(function (Product $product) use (&$count): void {
                $hay = trim($product->title.' '.$product->name_en.' '.$product->handle);
                if (! self::matches($hay)) {
                    return;
                }
                if (
                    $product->excluded_reason === CatalogExclusion::ADULT_CONTENT
                    && ! $product->visible
                    && ! $product->indexable
                ) {
                    return;
                }
                $product->excluded_reason = CatalogExclusion::ADULT_CONTENT;
                $product->visible = false;
                $product->indexable = false;
                $product->save();
                $count++;
            });

        return $count;
    }

    public static function excludeNamed(string $name, string $handle = '', string $remoteId = ''): void
    {
        $hay = trim($name.' '.$handle.' '.$remoteId);
        if ($hay === '' || ! self::matches($hay)) {
            return;
        }
        $query = Product::query()->where('handle', 'like', 'steam-gift-%');
        if ($handle !== '') {
            $query->where('handle', $handle);
        } elseif ($remoteId !== '') {
            $query->where(function ($inner) use ($remoteId): void {
                $inner->where('handle', 'steam-gift-'.$remoteId)
                    ->orWhere('fazercards_category_id', 'steam-gift:'.$remoteId);
            });
        } else {
            $query->where(function ($inner) use ($name): void {
                $inner->where('title', $name)->orWhere('name_en', $name);
            });
        }
        $query->orderBy('id')->each(function (Product $product): void {
            $product->excluded_reason = CatalogExclusion::ADULT_CONTENT;
            $product->visible = false;
            $product->indexable = false;
            $product->save();
        });
    }

    private static function storedText(): string
    {
        try {
            $value = Setting::getValue(self::SETTING_KEY);
        } catch (Throwable) {
            return '';
        }

        return is_string($value) ? trim($value) : '';
    }
}
