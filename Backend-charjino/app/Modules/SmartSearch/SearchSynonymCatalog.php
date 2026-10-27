<?php

namespace App\Modules\SmartSearch;

use App\Models\SearchSynonym;

final class SearchSynonymCatalog
{
    /**
     * @return list<array{term: string, synonyms: list<string>, group_slug: ?string}>
     */
    public static function defaults(): array
    {
        return [
            ['term' => 'pubg', 'synonyms' => ['پابجی', 'pubg mobile'], 'group_slug' => 'game-topup'],
            ['term' => 'uc', 'synonyms' => ['یوسی', 'uc'], 'group_slug' => 'game-topup'],
            ['term' => 'free fire', 'synonyms' => ['فری فایر', 'ff'], 'group_slug' => 'game-topup'],
            ['term' => 'call of duty', 'synonyms' => ['کالاف', 'cod', 'codm'], 'group_slug' => 'game-topup'],
            ['term' => 'cp', 'synonyms' => ['سی پی', 'سی‌پی'], 'group_slug' => 'game-topup'],
            ['term' => 'genshin', 'synonyms' => ['گنشین', 'genshin impact'], 'group_slug' => 'game-topup'],
            ['term' => 'hsr', 'synonyms' => ['honkai star rail'], 'group_slug' => 'game-topup'],
            ['term' => 'zzz', 'synonyms' => ['zenless zone zero'], 'group_slug' => 'game-topup'],
            ['term' => 'roblox', 'synonyms' => ['robux', 'روبلاکس'], 'group_slug' => 'game-topup'],
            ['term' => 'playstation', 'synonyms' => ['psn', 'ps', 'پلی', 'پلی استیشن', 'پلی‌استیشن'], 'group_slug' => 'gift-card'],
            ['term' => 'xbox', 'synonyms' => ['ایکس باکس', 'ایکس‌باکس'], 'group_slug' => 'gift-card'],
            ['term' => 'game pass', 'synonyms' => ['گیم پس'], 'group_slug' => 'gift-card'],
            ['term' => 'steam', 'synonyms' => ['استیم'], 'group_slug' => null],
            ['term' => 'ea fc', 'synonyms' => ['fifa', 'فیفا', 'fc'], 'group_slug' => 'game-keys'],
            ['term' => 'gta', 'synonyms' => ['gta v', 'جی تی ای'], 'group_slug' => 'game-keys'],
            ['term' => 'rdr2', 'synonyms' => ['red dead redemption 2'], 'group_slug' => 'game-keys'],
            ['term' => 'minecraft', 'synonyms' => ['ماینکرافت'], 'group_slug' => 'game-keys'],
            ['term' => 'gift card', 'synonyms' => ['گیفت کارت', 'گیفت'], 'group_slug' => 'gift-card'],
            ['term' => 'cd key', 'synonyms' => ['game key', 'کلید بازی'], 'group_slug' => 'game-keys'],
            ['term' => 'telegram premium', 'synonyms' => ['تلگرام پرمیوم'], 'group_slug' => 'telegram'],
            ['term' => 'stars', 'synonyms' => ['استارز', 'telegram stars'], 'group_slug' => 'telegram'],
            ['term' => 'global', 'synonyms' => ['گلوبال', 'جهانی'], 'group_slug' => null],
            ['term' => 'turkey', 'synonyms' => ['tr', 'ترکیه'], 'group_slug' => null],
            ['term' => 'uae', 'synonyms' => ['امارات'], 'group_slug' => null],
        ];
    }

    public static function seed(): int
    {
        $count = 0;
        foreach (self::defaults() as $row) {
            $term = PersianTextNormalizer::normalize($row['term']);
            $synonyms = [];
            foreach ($row['synonyms'] as $syn) {
                $n = PersianTextNormalizer::normalize($syn);
                if ($n !== '' && $n !== $term) {
                    $synonyms[$n] = $n;
                }
            }
            if ($term === '' || $synonyms === []) {
                continue;
            }
            $existing = SearchSynonym::query()->where('term', $term)->first();
            if ($existing) {
                $merged = array_values(array_unique(array_merge((array) $existing->synonyms, array_values($synonyms))));
                if ($merged !== array_values((array) $existing->synonyms)) {
                    $existing->update(['synonyms' => $merged]);
                    $count++;
                }
                continue;
            }
            SearchSynonym::query()->create([
                'term' => $term,
                'synonyms' => array_values($synonyms),
                'group_slug' => $row['group_slug'],
                'is_active' => true,
            ]);
            $count++;
        }

        return $count;
    }
}
