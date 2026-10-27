<?php

namespace App\Modules\SmartSearch;

final class RegionParser
{
    /** @var array<string, list<string>> */
    private const ALIASES = [
        'global' => ['global', 'globally', 'worldwide', 'جهانی', 'گلوبال'],
        'row' => ['row', 'rest of world'],
        'us' => ['us', 'usa', 'united states', 'آمریکا', 'امریکا'],
        'eu' => ['eu', 'europe', 'اروپا'],
        'uk' => ['uk', 'united kingdom', 'britain', 'انگلیس', 'بریتانیا'],
        'tr' => ['tr', 'turkey', 'türkiye', 'ترکیه'],
        'cis' => ['cis'],
        'ru' => ['ru', 'russia', 'روسیه'],
        'uae' => ['uae', 'ae', 'emirates', 'امارات'],
    ];

    /**
     * @return list<string>
     */
    public static function parse(string ...$haystacks): array
    {
        $blob = PersianTextNormalizer::normalize(mb_strtolower(implode(' ', array_filter($haystacks))));
        $found = [];
        foreach (self::ALIASES as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $alias = PersianTextNormalizer::normalize($alias);
                if ($alias === '') {
                    continue;
                }
                if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($alias, '/').'(?![\p{L}\p{N}])/u', $blob) === 1) {
                    $found[$canonical] = $canonical;
                    break;
                }
            }
        }
        $out = array_values($found);
        sort($out);

        return $out;
    }
}
