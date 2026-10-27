<?php

namespace App\Modules\BuyGuide;

use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

final class BuyGuideHtml
{
    public static function sanitize(string $html): string
    {
        $html = BuyGuideContentRules::cleanText($html);
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $allowed = '<p><h3><ul><ol><li><strong><em><a><br><blockquote><code>';
        $clean = trim(strip_tags($html, $allowed));
        $clean = preg_replace('/\s(on\w+|style|class)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $clean) ?? $clean;
        $clean = preg_replace_callback('/<a\s+([^>]+)>/i', function (array $match): string {
            $attrs = $match[1];
            $href = '';
            if (preg_match('/href\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $hrefMatch) === 1) {
                $href = $hrefMatch[2] !== '' ? $hrefMatch[2] : ($hrefMatch[3] ?? '');
            }
            $href = trim($href);
            if ($href === '' || ! BuyGuideLinkPolicy::allowsUrl($href)) {
                return '<a data-denied="1">';
            }

            return '<a href="'.htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8').'" rel="nofollow noopener noreferrer" target="_blank">';
        }, $clean) ?? $clean;
        $clean = preg_replace('/<a data-denied="1">(.*?)<\/a>/is', '$1', $clean) ?? $clean;

        return $clean;
    }

    public static function fromMarkdown(string $markdown): string
    {
        $markdown = BuyGuideInternalBlocks::strip($markdown);
        if (trim($markdown) === '') {
            return '';
        }
        $parser = app(BuyGuideMarkdownParser::class);

        return $parser->renderFragment($markdown);
    }

    public static function paragraphs(string $plain): string
    {
        $text = BuyGuidePlainText::from($plain);
        if ($text === '') {
            return '';
        }
        $chunks = preg_split("/\n{2,}/", $plain) ?: [$plain];
        $html = '';
        foreach ($chunks as $chunk) {
            $line = BuyGuidePlainText::from($chunk);
            if ($line === '') {
                continue;
            }
            $html .= '<p>'.htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
        }

        return $html;
    }

    public static function escape(string $text): string
    {
        return htmlspecialchars(BuyGuideContentRules::cleanText($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function inline(Node $node): string
    {
        $html = '';
        foreach ($node->children() as $child) {
            $html .= self::inlineNode($child);
        }

        return $html;
    }

    private static function inlineNode(Node $node): string
    {
        if ($node instanceof Text) {
            return self::escape($node->getLiteral());
        }
        if ($node instanceof Newline) {
            return ' ';
        }
        if ($node instanceof Strong) {
            return '<strong>'.self::inline($node).'</strong>';
        }
        if ($node instanceof Emphasis) {
            return '<em>'.self::inline($node).'</em>';
        }
        if ($node instanceof Code) {
            return '<code>'.self::escape($node->getLiteral()).'</code>';
        }
        if ($node instanceof Link) {
            $url = trim($node->getUrl());
            $inner = self::inline($node);
            if (BuyGuideLinkPolicy::allowsUrl($url)) {
                return '<a href="'.self::escape($url).'" rel="nofollow noopener noreferrer" target="_blank">'.$inner.'</a>';
            }

            return $inner;
        }
        if ($node->hasChildren()) {
            return self::inline($node);
        }

        return '';
    }
}
