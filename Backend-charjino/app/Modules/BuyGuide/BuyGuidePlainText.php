<?php

namespace App\Modules\BuyGuide;

final class BuyGuidePlainText
{
    public static function from(string $value): string
    {
        $text = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<br\s*\/?>/i', ' ', $text) ?? $text;
        $text = strip_tags($text);
        $text = preg_replace('/!\[[^\]]*]\([^)]+\)/', '', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/[*_`#]+/', '', $text) ?? $text;
        $text = str_replace(['>', '|'], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return BuyGuideContentRules::cleanText($text);
    }
}
