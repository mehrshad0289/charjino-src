<?php

namespace App\Modules\BuyGuide;

final class BuyGuideInternalBlocks
{
    public static function strip(string $markdown): string
    {
        $text = preg_replace('/<!--\s*internal\s-->.*?<!--\s*\/internal\s-->/is', "\n", $markdown) ?? $markdown;
        $text = preg_replace('/:::internal\b.*?:::/is', "\n", $text) ?? $text;

        return trim($text)."\n";
    }
}
