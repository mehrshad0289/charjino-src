<?php

namespace Tests\Feature;

use App\Modules\Blog\BlogContentRules;
use App\Modules\Blog\BlogHtml;
use App\Modules\Blog\GuideArticles;
use Tests\TestCase;

class GuideArticlesContentTest extends TestCase
{
    public function test_guide_articles_meet_length_heading_link_and_meta_rules(): void
    {
        $items = GuideArticles::all();
        $this->assertCount(11, $items);
        $titles = [];
        $metas = [];
        $metaTitles = [];
        $slugs = [];
        foreach ($items as $item) {
            $slug = (string) $item['slug'];
            $content = (string) $item['content'];
            $words = BlogHtml::wordCount($content);
            $this->assertGreaterThanOrEqual(1200, $words, $slug);
            $this->assertLessThanOrEqual(2000, $words, $slug);
            BlogContentRules::assertPublishable($content);
            $meta = (string) $item['metaDescription'];
            BlogContentRules::assertMetaDescription($meta);
            $title = (string) $item['title'];
            $this->assertGreaterThanOrEqual(10, mb_strlen($title));
            $this->assertLessThanOrEqual(100, mb_strlen($title));
            $this->assertLessThanOrEqual(80, mb_strlen((string) $item['metaTitle']));
            $this->assertGreaterThanOrEqual(4, preg_match_all('/<h2\b/i', $content));
            $this->assertGreaterThanOrEqual(2, preg_match_all('/<h3\b/i', $content));
            preg_match_all('/<a\s+[^>]*href="(\/catalog\/[^"]+)"/i', $content, $catalogHrefs);
            $links = count(array_unique($catalogHrefs[1] ?? []));
            $this->assertGreaterThanOrEqual(2, $links, $slug);
            $this->assertLessThanOrEqual(4, $links, $slug);
            $this->assertGreaterThanOrEqual(4, count($item['faq'] ?? []));
            $slugs[] = $slug;
            $titles[] = $title;
            $metas[] = $meta;
            $metaTitles[] = (string) $item['metaTitle'];
        }
        $this->assertCount(11, array_unique($slugs));
        $this->assertCount(11, array_unique($titles));
        $this->assertCount(11, array_unique($metas));
        $this->assertCount(11, array_unique($metaTitles));
    }
}
