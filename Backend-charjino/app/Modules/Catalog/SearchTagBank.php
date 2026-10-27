<?php

namespace App\Modules\Catalog;

use App\Models\Product;
use App\Models\SearchTag;

final class SearchTagBank
{
    /**
     * برچسب‌هایی که در نام فارسی/انگلیسی محصول نیستند ولی برای جستجو و سئو لازم‌اند.
     *
     * @return list<array{slug: string, label: string, aliases: list<string>, pattern: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'slug' => 'football',
                'label' => 'فوتبال',
                'aliases' => ['فوتبال', 'football', 'fifa', 'فیفا', 'soccer', 'fc'],
                'pattern' => '/fc\s*2\d|fifa|\bea sports fc\b/iu',
            ],
            [
                'slug' => 'call-of-duty',
                'label' => 'کالاف دیوتی',
                'aliases' => ['کالاف', 'کالاف دیوتی', 'call of duty', 'warzone', 'cod'],
                'pattern' => '/call of duty|warzone|\bcod\b|کالاف/iu',
            ],
            [
                'slug' => 'pubg',
                'label' => 'پابجی',
                'aliases' => ['پابجی', 'pubg', 'battlegrounds'],
                'pattern' => '/pubg|پابجی|battlegrounds/iu',
            ],
            [
                'slug' => 'roblox',
                'label' => 'روبلاکس',
                'aliases' => ['روبلاکس', 'roblox'],
                'pattern' => '/roblox|روبلاکس/iu',
            ],
            [
                'slug' => 'fortnite',
                'label' => 'فورتنایت',
                'aliases' => ['فورتنایت', 'fortnite'],
                'pattern' => '/fortnite|فورتنایت/iu',
            ],
            [
                'slug' => 'valorant',
                'label' => 'ولورانت',
                'aliases' => ['ولورانت', 'valorant'],
                'pattern' => '/valorant|ولورانت/iu',
            ],
            [
                'slug' => 'gta',
                'label' => 'جی تی ای',
                'aliases' => ['جی تی ای', 'gta', 'grand theft auto'],
                'pattern' => '/\bgta\b|grand theft auto|جی[\s\-]*تی[\s\-]*ای/iu',
            ],
            [
                'slug' => 'minecraft',
                'label' => 'ماینکرافت',
                'aliases' => ['ماینکرافت', 'minecraft'],
                'pattern' => '/minecraft|ماینکرافت/iu',
            ],
        ];
    }

    /**
     * @return array{tags: int, attached: int}
     */
    public static function sync(): array
    {
        $tags = [];
        foreach (self::definitions() as $def) {
            $aliases = array_values(array_unique(array_map(
                fn (string $alias) => SearchQuery::normalize($alias),
                $def['aliases'],
            )));
            $aliasesText = SearchQuery::normalize($def['label'].' '.implode(' ', $aliases));
            $tag = SearchTag::query()->updateOrCreate(
                ['slug' => $def['slug']],
                [
                    'label' => $def['label'],
                    'aliases' => $aliases,
                    'aliases_text' => $aliasesText,
                    'attach_pattern' => $def['pattern'],
                ],
            );
            $tags[$def['slug']] = $tag;
        }

        $attached = 0;
        Product::query()
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($tags, &$attached): void {
                foreach ($rows as $product) {
                    if (! $product instanceof Product) {
                        continue;
                    }
                    $hay = trim((string) $product->title.' '.(string) $product->name_en);
                    $ids = [];
                    foreach (self::definitions() as $def) {
                        if (preg_match($def['pattern'], $hay) !== 1) {
                            continue;
                        }
                        $tag = $tags[$def['slug']] ?? null;
                        if ($tag instanceof SearchTag) {
                            $ids[] = $tag->id;
                        }
                    }
                    if ($ids === []) {
                        continue;
                    }
                    $product->searchTags()->syncWithoutDetaching($ids);
                    $attached++;
                }
            });

        return [
            'tags' => count($tags),
            'attached' => $attached,
        ];
    }
}
