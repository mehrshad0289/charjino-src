<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Modules\BuyGuide\BuyGuideMarkdownParser;
use App\Modules\BuyGuide\BuyGuidePlainText;
use App\Modules\BuyGuide\BuyGuidePublicValidator;
use Tests\TestCase;

class BuyGuidePipelineTest extends TestCase
{
    public function test_public_payload_drops_unnamed_sections_and_internal_blocks(): void
    {
        $markdown = file_get_contents(base_path('tests/fixtures/buy-guides/clean.md'));
        $payload = app(BuyGuideMarkdownParser::class)->parse((string) $markdown, [
            'scope' => 'subcategory',
            'category' => 'game-topup',
            'subcategory' => 'sample',
        ], sys_get_temp_dir());

        $this->assertStringNotContainsString('INDEX.md', $payload['summary']);
        $this->assertStringNotContainsString('INDEX.md', $payload['intro']);
        $this->assertStringNotContainsString('**', $payload['intro']);
        $this->assertStringNotContainsString('سناریو', json_encode($payload['sections'], JSON_UNESCAPED_UNICODE));
        $types = array_column($payload['sections'], 'type');
        $this->assertContains('before_buy', $types);
        $this->assertContains('faq', $types);
        $this->assertContains('videos', $types);
        $this->assertContains('sources', $types);
        $videos = collect($payload['sections'])->firstWhere('type', 'videos');
        $this->assertCount(1, $videos['data']['items']);
        $sources = collect($payload['sections'])->firstWhere('type', 'sources');
        $this->assertStringContainsString('midasbuy.com', $sources['data']['items'][0]['url']);
        $this->assertMatchesSnapshot($this->publicSnapshot($payload));
    }

    public function test_leak_fixture_types_never_reach_public_output(): void
    {
        $markdown = file_get_contents(base_path('tests/fixtures/buy-guides/leaks.md'));
        try {
            app(BuyGuideMarkdownParser::class)->parse((string) $markdown, [
                'scope' => 'subcategory',
                'category' => 'game-keys',
                'subcategory' => 'steam',
            ], sys_get_temp_dir());
            $payload = null;
        } catch (ApiException $e) {
            $this->assertNotSame('', $e->getMessage());
            $payload = null;
        }
        if ($payload !== null) {
            $flat = json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '';
            $this->assertStringNotContainsString('طبق فرم محصول', $flat);
            $this->assertStringNotContainsString('bazelio', $flat);
            $this->assertStringNotContainsString('codashop', $flat);
            $this->assertStringNotContainsString('|---|', $flat);
            $this->assertStringNotContainsString('###', $flat);
        }
    }

    public function test_internal_comment_and_fence_are_stripped(): void
    {
        $markdown = <<<'MD'
# عنوان

## ۱. خلاصه

متن عمومی.

<!-- internal -->
46,972 صفحه INDEX.md
<!-- /internal -->

:::internal
رقیب iranmojo و https://recharge.com
:::

## ۹. سؤالات متداول

**سؤال؟**
جواب.
MD;
        $payload = app(BuyGuideMarkdownParser::class)->parse($markdown, [
            'scope' => 'category',
            'category' => 'gift-card',
        ], sys_get_temp_dir());
        $flat = json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '';
        $this->assertStringNotContainsString('46,972', $flat);
        $this->assertStringNotContainsString('INDEX.md', $flat);
        $this->assertStringNotContainsString('iranmojo', $flat);
        $this->assertStringNotContainsString('recharge.com', $flat);
    }

    public function test_plain_text_strips_markdown_for_seo(): void
    {
        $this->assertSame(
            'خرید الماس فری فایر از شارژینو',
            BuyGuidePlainText::from('**خرید** الماس فری فایر از شارژینو'),
        );
        $this->expectException(ApiException::class);
        BuyGuidePublicValidator::assertText('توضیح با **باقی‌مانده**');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publicSnapshot(array $payload): string
    {
        unset($payload['_images'], $payload['lastReviewedAt']);

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
    }

    private function assertMatchesSnapshot(string $actual): void
    {
        $file = base_path('tests/fixtures/buy-guides/clean.public.json');
        if (! is_file($file)) {
            file_put_contents($file, $actual);
            $this->assertFileExists($file);

            return;
        }
        $this->assertSame(trim((string) file_get_contents($file)), trim($actual));
    }
}
