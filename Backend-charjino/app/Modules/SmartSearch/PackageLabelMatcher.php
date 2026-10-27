<?php

namespace App\Modules\SmartSearch;

final class PackageLabelMatcher
{
    /**
     * @param  list<string>  $labels
     */
    public static function match(string $query, array $labels): ?string
    {
        if (! preg_match('/(\d+)\s*([A-Za-z\p{L}]+)/u', $query, $m)) {
            return null;
        }
        $want = mb_strtolower($m[1].' '.$m[2]);
        $best = null;
        $bestScore = 0;
        foreach ($labels as $label) {
            $norm = mb_strtolower(PersianTextNormalizer::normalize($label));
            if ($norm === '') {
                continue;
            }
            $score = 0;
            if ($norm === $want) {
                $score = 3;
            } elseif (str_contains($norm, $want) || str_contains($want, $norm)) {
                $score = 2;
            } elseif (str_contains($norm, $m[1])) {
                $score = 1;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $label;
            }
        }

        return $bestScore > 0 ? $best : null;
    }
}
