<?php

namespace App\Modules\Catalog;

final class RegionNote
{
    public static function fa(?string $noteEn, string $name = ''): ?string
    {
        $en = trim((string) $noteEn);
        $hay = mb_strtolower($en.' '.$name);
        if ($hay === '') {
            return null;
        }
        if (preg_match('/mobile legends|\bmlbb\b|\bml\b/', $hay) === 1) {
            return 'برای حساب‌های ریجن اندونزی و برزیل نیست. چند بسته برای MY/SG/PH/ID/RU در دسترس نیست.';
        }
        if (str_contains($hay, 'valorant')) {
            return 'حساب Riot باید در ریجن همین محصول باشد.';
        }
        if (str_contains($hay, 'free fire') && str_contains($hay, 'mena')) {
            return 'فقط برای حساب‌های Free Fire ریجن خاورمیانه و شمال آفریقا.';
        }
        if (preg_match('/region:\s*(.+)/i', $en, $match) === 1) {
            $region = trim((string) strstr($match[1], "\n", true) ?: $match[1]);
            $region = trim($region, " \t.;");
            if ($region !== '') {
                return 'این محصول برای ریجن '.$region.' است.';
            }
        }

        return null;
    }
}
