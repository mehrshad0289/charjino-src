<?php

namespace App\Modules\BuyGuide;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;

final class BuyGuideMarkdownParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $markdown, array $meta, string $baseDir): array
    {
        $markdown = BuyGuideInternalBlocks::strip($markdown);
        $document = $this->parseDocument($markdown);
        $title = '';
        $buckets = [];
        $current = null;
        $skipDepth = 0;
        foreach ($document->children() as $child) {
            if ($child instanceof Heading && $child->getLevel() === 1 && $title === '') {
                $title = BuyGuideContentRules::cleanText($this->textOf($child));
                continue;
            }
            if ($child instanceof Heading && $child->getLevel() === 2) {
                $skipDepth = 0;
                $current = BuyGuideHeading::key($this->textOf($child));
                continue;
            }
            if ($child instanceof Heading && $child->getLevel() >= 3) {
                $skipDepth = BuyGuideHeading::isInternalSubheading($this->textOf($child)) ? $child->getLevel() : 0;
                if ($skipDepth > 0) {
                    continue;
                }
            } elseif ($skipDepth > 0) {
                continue;
            }
            if ($current === null) {
                continue;
            }
            $buckets[$current][] = $child;
        }

        $summaryHtml = $this->renderNodes($buckets['summary'] ?? []);
        $summary = BuyGuidePlainText::from($summaryHtml);
        $before = $this->beforeBuy($buckets['before_buy'] ?? []);
        $fields = $this->fields($buckets['fields'] ?? [], $baseDir);
        $purchase = $this->stepsWithImage($buckets['purchase_steps'] ?? [], $baseDir);
        $redeem = $this->redeem($buckets['redeem'] ?? [], $baseDir);
        $videos = $this->videos($buckets['videos'] ?? []);
        $errors = $this->errors($buckets['errors'] ?? []);
        $faq = $this->faq($buckets['faq'] ?? []);
        $sources = $this->sources($buckets['sources'] ?? []);

        $sections = array_values(array_filter([
            $before ? ['type' => 'before_buy', 'inherit' => false, 'data' => $before] : null,
            $fields ? ['type' => 'fields', 'inherit' => false, 'data' => $fields] : null,
            $purchase ? ['type' => 'purchase_steps', 'inherit' => false, 'data' => $purchase] : null,
            $redeem ? ['type' => 'redeem', 'inherit' => false, 'data' => $redeem] : null,
            $errors ? ['type' => 'errors', 'inherit' => false, 'data' => $errors] : null,
            $faq ? ['type' => 'faq', 'inherit' => false, 'data' => $faq] : null,
            $videos ? ['type' => 'videos', 'inherit' => false, 'data' => $videos] : null,
            $sources ? ['type' => 'sources', 'inherit' => false, 'data' => $sources] : null,
        ]));

        $payload = [
            'scopeType' => $meta['scope'] ?? 'subcategory',
            'category' => $meta['category'] ?? '',
            'subcategory' => $meta['subcategory'] ?? '',
            'title' => $title,
            'summary' => $summary,
            'intro' => $summaryHtml,
            'lastReviewedAt' => $meta['lastReviewedAt'] ?? now()->toDateString(),
            'status' => $meta['status'] ?? 'draft',
            'sections' => $sections,
            '_images' => $this->collectImages($markdown, $baseDir),
        ];
        $check = $payload;
        unset($check['_images']);
        BuyGuidePublicValidator::assertPayload($check);

        return $payload;
    }

    public function renderFragment(string $markdown): string
    {
        $document = $this->parseDocument(BuyGuideInternalBlocks::strip($markdown));

        return BuyGuideHtml::sanitize($this->renderNodes(iterator_to_array($document->children())));
    }

    private function parseDocument(string $markdown): Node
    {
        $environment = new Environment;
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $parser = new MarkdownParser($environment);

        return $parser->parse($markdown);
    }

    /**
     * @param  list<Node>  $nodes
     */
    private function renderNodes(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            if ($node instanceof Table || $node instanceof Heading) {
                continue;
            }
            if ($node instanceof Paragraph) {
                $inner = BuyGuideHtml::inline($node);
                if (trim(strip_tags($inner)) !== '') {
                    $html .= '<p>'.$inner.'</p>';
                }
                continue;
            }
            if ($node instanceof BlockQuote) {
                $inner = '';
                foreach ($node->children() as $child) {
                    $inner .= BuyGuideHtml::inline($child);
                }
                if (trim(strip_tags($inner)) !== '') {
                    $html .= '<blockquote>'.$inner.'</blockquote>';
                }
                continue;
            }
            if ($node instanceof ListBlock) {
                $tag = $node->getListData()->type === ListBlock::TYPE_ORDERED ? 'ol' : 'ul';
                $items = '';
                foreach ($node->children() as $item) {
                    if (! $item instanceof ListItem) {
                        continue;
                    }
                    $items .= '<li>'.BuyGuideHtml::inline($item).'</li>';
                }
                if ($items !== '') {
                    $html .= '<'.$tag.'>'.$items.'</'.$tag.'>';
                }
            }
        }

        return BuyGuideHtml::sanitize($html);
    }

    /**
     * @param  list<Node>  $nodes
     * @return array{notes: list<array{text: string, tone: string}>}|null
     */
    private function beforeBuy(array $nodes): ?array
    {
        $notes = [];
        foreach ($nodes as $node) {
            if ($node instanceof ListBlock) {
                foreach ($node->children() as $item) {
                    if (! $item instanceof ListItem) {
                        continue;
                    }
                    $text = BuyGuidePlainText::from(BuyGuideHtml::inline($item));
                    if ($text === '' || BuyGuidePublicCopy::isInternal($text)) {
                        continue;
                    }
                    $tone = preg_match('/هشدار|VPN|خلاف|مسئولیت/u', $text) === 1 ? 'warning' : 'info';
                    $notes[] = ['text' => $text, 'tone' => $tone];
                }
            }
        }

        return $notes === [] ? null : ['notes' => $notes];
    }

    /**
     * @param  list<Node>  $nodes
     * @return array{items: list<array{name: string, label: string, help: string}>}|null
     */
    private function fields(array $nodes, string $baseDir): ?array
    {
        $items = [];
        foreach ($this->tables($nodes) as $rows) {
            foreach ($rows as $index => $row) {
                $label = $row[0] ?? '';
                $help = $row[1] ?? '';
                if ($index === 0 && (in_array($label, ['فیلد', 'Field'], true) || in_array($help, ['توضیح', 'Help'], true))) {
                    continue;
                }
                if ($label === '' || str_contains($label, '---')) {
                    continue;
                }
                $items[] = [
                    'name' => BuyGuideScope::normSub($label) ?: 'field',
                    'label' => $label,
                    'help' => $help,
                ];
            }
        }
        $image = $this->firstImage($nodes, $baseDir);
        if ($image && $items !== []) {
            $items[0]['image'] = $image['path'];
            $items[0]['alt'] = $image['alt'];
            $items[0]['_abs'] = $image['abs'];
        }

        return $items === [] ? null : ['items' => $items];
    }

    /**
     * @param  list<Node>  $nodes
     * @return array{steps: list<array{text: string, image?: string, alt?: string}>}|null
     */
    private function stepsWithImage(array $nodes, string $baseDir): ?array
    {
        $steps = $this->ordered($nodes);
        $image = $this->firstImage($nodes, $baseDir);
        if ($steps === [] && $image === null) {
            return null;
        }
        if ($image && $steps !== []) {
            $steps[0]['image'] = $image['path'];
            $steps[0]['alt'] = $image['alt'];
            $steps[0]['_abs'] = $image['abs'];
        } elseif ($image) {
            $steps[] = ['text' => $image['alt'], 'image' => $image['path'], 'alt' => $image['alt'], '_abs' => $image['abs']];
        }

        return ['steps' => $steps];
    }

    /**
     * @param  list<Node>  $nodes
     * @return array{tabs: list<array<string, mixed>>}|null
     */
    private function redeem(array $nodes, string $baseDir): ?array
    {
        $tabs = [];
        $buffer = [];
        $label = '';
        $flush = function () use (&$tabs, &$buffer, &$label, $baseDir): void {
            if ($label === '' && $buffer === []) {
                return;
            }
            $steps = $this->ordered($buffer);
            $image = $this->firstImage($buffer, $baseDir);
            $tabLabel = $label !== '' ? $label : 'فعال‌سازی';
            $tab = [
                'key' => BuyGuideScope::normSub($tabLabel) ?: 'tab-'.count($tabs),
                'label' => $tabLabel,
                'steps' => $steps,
            ];
            if ($image) {
                $tab['image'] = $image['path'];
                $tab['alt'] = $image['alt'];
                $tab['_abs'] = $image['abs'];
                if ($steps !== []) {
                    $tab['steps'][0]['image'] = $image['path'];
                    $tab['steps'][0]['alt'] = $image['alt'];
                    $tab['steps'][0]['_abs'] = $image['abs'];
                }
            }
            if ($steps !== [] || $image) {
                $tabs[] = $tab;
            }
            $buffer = [];
        };
        foreach ($nodes as $node) {
            if ($node instanceof Heading && $node->getLevel() >= 3) {
                $flush();
                $label = BuyGuideContentRules::cleanText($this->textOf($node));
                continue;
            }
            $buffer[] = $node;
        }
        $flush();
        if ($tabs === []) {
            $fallback = $this->stepsWithImage($nodes, $baseDir);
            if ($fallback) {
                $tabs[] = [
                    'key' => 'default',
                    'label' => 'فعال‌سازی',
                    'steps' => $fallback['steps'],
                ];
            }
        }

        return $tabs === [] ? null : ['tabs' => $tabs];
    }

    /**
     * @param  list<Node>  $nodes
     * @return array{items: list<array<string, mixed>>}|null
     */
    private function videos(array $nodes): ?array
    {
        $items = [];
        foreach ($nodes as $node) {
            if (! $node instanceof Table) {
                continue;
            }
            foreach ($node->iterator() as $row) {
                if (! $row instanceof TableRow) {
                    continue;
                }
                $cells = [];
                $url = '';
                $title = '';
                foreach ($row->children() as $cell) {
                    if (! $cell instanceof TableCell) {
                        continue;
                    }
                    $cells[] = BuyGuidePlainText::from(BuyGuideHtml::inline($cell));
                    foreach ($this->linksIn($cell) as $link) {
                        if (BuyGuideLinkPolicy::allowsVideoUrl($link['url'])) {
                            $url = $link['url'];
                            $title = $link['label'] !== '' ? $link['label'] : $title;
                        }
                    }
                }
                if ($url === '') {
                    continue;
                }
                $host = BuyGuideLinkPolicy::hostOf($url);
                $langCell = mb_strtolower($cells[2] ?? '');
                $items[] = [
                    'provider' => str_contains($host, 'aparat') ? 'aparat' : 'youtube',
                    'url' => $url,
                    'title' => $title !== '' ? $title : ($cells[0] ?? $url),
                    'lang' => str_contains($langCell, 'انگلیس') || str_contains($langCell, 'en') ? 'en' : 'fa',
                    'duration' => $cells[3] ?? '',
                ];
            }
        }

        return $items === [] ? null : ['items' => $items];
    }

    /**
     * @param  list<Node>  $nodes
     * @return array{items: list<array<string, string>>}|null
     */
    private function errors(array $nodes): ?array
    {
        $items = [];
        foreach ($this->tables($nodes) as $rows) {
            foreach ($rows as $index => $row) {
                $a = $row[0] ?? '';
                $b = $row[count($row) - 1] ?? ($row[1] ?? '');
                if ($a === '' || in_array($a, ['خطا', 'سؤال', 'مشکل'], true) || $index === 0 && str_contains($a, 'خطا')) {
                    continue;
                }
                $items[] = ['error' => $a, 'fix' => $b];
            }
        }

        return $items === [] ? null : ['items' => $items];
    }

    /**
     * @param  list<Node>  $nodes
     * @return array{items: list<array{q: string, a: string}>}|null
     */
    private function faq(array $nodes): ?array
    {
        $items = [];
        $pendingQ = '';
        $pendingA = [];
        $flush = function () use (&$items, &$pendingQ, &$pendingA): void {
            $q = BuyGuidePlainText::from($pendingQ);
            $a = BuyGuidePlainText::from(implode(' ', $pendingA));
            $pendingQ = '';
            $pendingA = [];
            if ($q === '' || $a === '' || BuyGuidePublicCopy::isInternal($q.' '.$a)) {
                return;
            }
            $items[] = ['q' => $q, 'a' => $a];
        };
        foreach ($nodes as $node) {
            if ($node instanceof Table) {
                continue;
            }
            if ($node instanceof Heading && $node->getLevel() >= 3) {
                $flush();
                $heading = BuyGuideContentRules::cleanText($this->textOf($node));
                if (str_contains($heading, '؟') || str_contains($heading, '?')) {
                    $pendingQ = $heading;
                }
                continue;
            }
            if ($node instanceof Paragraph) {
                $strong = $this->leadingStrong($node);
                $rest = BuyGuidePlainText::from(BuyGuideHtml::inline($node));
                if ($strong !== '') {
                    $flush();
                    $pendingQ = $strong;
                    $after = trim(preg_replace('/^'.preg_quote($strong, '/').'/u', '', $rest) ?? $rest);
                    if ($after !== '') {
                        $pendingA[] = $after;
                    }
                    continue;
                }
                if ($pendingQ !== '') {
                    $pendingA[] = $rest;
                }
            }
        }
        $flush();

        return $items === [] ? null : ['items' => $items];
    }

    /**
     * @param  list<Node>  $nodes
     * @return array{items: list<array{url: string, label: string}>}|null
     */
    private function sources(array $nodes): ?array
    {
        $items = [];
        foreach ($nodes as $node) {
            foreach ($this->linksIn($node) as $link) {
                $url = rtrim($link['url'], '.,');
                $label = $link['label'] !== '' ? $link['label'] : $url;
                if (! BuyGuideLinkPolicy::allowsUrl($url) || BuyGuidePublicCopy::isInternal($label.' '.$url)) {
                    continue;
                }
                $items[] = ['url' => $url, 'label' => $label];
            }
            if ($node instanceof ListBlock) {
                foreach ($node->children() as $item) {
                    $line = BuyGuidePlainText::from(BuyGuideHtml::inline($item));
                    $url = $this->firstHttpUrl($line);
                    if ($url === '' || ! BuyGuideLinkPolicy::allowsUrl($url) || BuyGuidePublicCopy::isInternal($line)) {
                        continue;
                    }
                    $items[] = ['url' => $url, 'label' => $line];
                }
            }
        }
        $unique = [];
        foreach ($items as $item) {
            $unique[$item['url']] = $item;
        }

        return $unique === [] ? null : ['items' => array_values($unique)];
    }

    /**
     * @param  list<Node>  $nodes
     * @return list<array{text: string}>
     */
    private function ordered(array $nodes): array
    {
        $steps = [];
        foreach ($nodes as $node) {
            if (! $node instanceof ListBlock || $node->getListData()->type !== ListBlock::TYPE_ORDERED) {
                continue;
            }
            foreach ($node->children() as $item) {
                if (! $item instanceof ListItem) {
                    continue;
                }
                $text = BuyGuidePlainText::from(BuyGuideHtml::inline($item));
                if ($text !== '') {
                    $steps[] = ['text' => $text];
                }
            }
        }

        return $steps;
    }

    /**
     * @param  list<Node>  $nodes
     * @return list<list<string>>
     */
    private function tables(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (! $node instanceof Table) {
                continue;
            }
            $rows = [];
            foreach ($node->children() as $section) {
                if (! $section instanceof TableSection) {
                    continue;
                }
                foreach ($section->children() as $row) {
                    if (! $row instanceof TableRow) {
                        continue;
                    }
                    $cells = [];
                    foreach ($row->children() as $cell) {
                        if ($cell instanceof TableCell) {
                            $cells[] = BuyGuidePlainText::from(BuyGuideHtml::inline($cell));
                        }
                    }
                    if ($cells !== [] && ! str_contains($cells[0], '---')) {
                        $rows[] = $cells;
                    }
                }
            }
            if ($rows !== []) {
                $out[] = $rows;
            }
        }

        return $out;
    }

    /**
     * @param  list<Node>  $nodes
     * @return array{path: string, alt: string, abs: string}|null
     */
    private function firstImage(array $nodes, string $baseDir): ?array
    {
        foreach ($nodes as $node) {
            foreach ($node->iterator() as $child) {
                if ($child instanceof Image) {
                    $path = $child->getUrl();
                    $alt = BuyGuideContentRules::cleanText($this->textOf($child)) ?: 'تصویر راهنمای خرید';

                    return ['path' => $path, 'alt' => $alt, 'abs' => $this->abs($baseDir, $path)];
                }
            }
        }

        return null;
    }

    /**
     * @return list<array{path: string, alt: string, abs: string}>
     */
    private function collectImages(string $markdown, string $baseDir): array
    {
        $out = [];
        $document = $this->parseDocument($markdown);
        foreach ($document->iterator() as $node) {
            if ($node instanceof Image) {
                $path = $node->getUrl();
                $out[] = [
                    'alt' => BuyGuideContentRules::cleanText($this->textOf($node)) ?: 'تصویر راهنمای خرید',
                    'path' => $path,
                    'abs' => $this->abs($baseDir, $path),
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<array{url: string, label: string}>
     */
    private function linksIn(Node $node): array
    {
        $out = [];
        foreach ($node->iterator() as $child) {
            if ($child instanceof Link) {
                $out[] = [
                    'url' => $child->getUrl(),
                    'label' => BuyGuidePlainText::from(BuyGuideHtml::inline($child)),
                ];
            }
        }

        return $out;
    }

    private function leadingStrong(Paragraph $node): string
    {
        $first = $node->firstChild();
        if ($first instanceof Strong) {
            return BuyGuidePlainText::from(BuyGuideHtml::inline($first));
        }

        return '';
    }

    private function textOf(Node $node): string
    {
        $buf = '';
        foreach ($node->iterator() as $child) {
            if ($child instanceof Text) {
                $buf .= $child->getLiteral();
            }
        }

        return BuyGuideContentRules::cleanText($buf);
    }

    private function abs(string $baseDir, string $rel): string
    {
        $rel = str_replace(['\\'], '/', $rel);
        if (preg_match('#^([a-z]:)?/#i', $rel) === 1 || str_starts_with($rel, 'http')) {
            return $rel;
        }

        return rtrim($baseDir, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
    }

    private function firstHttpUrl(string $text): string
    {
        if (preg_match('#https?://[^\s]+#', $text, $match) === 1) {
            return rtrim($match[0], '.,)');
        }

        return '';
    }
}
