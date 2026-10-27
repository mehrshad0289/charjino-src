<?php

namespace Tests\Unit;

use App\Modules\Catalog\HomeHtml;
use Tests\TestCase;

class HomeHtmlTest extends TestCase
{
    public function test_default_intro_has_hub_keywords_and_internal_links(): void
    {
        $html = HomeHtml::default();
        $this->assertStringContainsString('یوسی پابجی', $html);
        $this->assertStringContainsString('سی‌پی کالاف دیوتی', $html);
        $this->assertStringContainsString('گیفت کارت گوگل پلی', $html);
        $this->assertStringContainsString('گیفت کارت استیم/پلی‌استیشن', strip_tags($html));
        $this->assertStringContainsString('الماس فری فایر', $html);
        $this->assertStringContainsString('/catalog/category/game-topup', $html);
        $this->assertStringContainsString('/catalog/category/call-of-duty-mobile', $html);
        $this->assertStringContainsString('/catalog/category/google-play', $html);
        $this->assertStringContainsString('/catalog/category/steam-wallet', $html);
        $this->assertStringContainsString('/catalog/category/playstation', $html);
        $this->assertStringContainsString('/catalog/category/free-fire', $html);
    }

    public function test_empty_html_falls_back_to_default(): void
    {
        $this->assertSame(HomeHtml::default(), HomeHtml::sanitize(''));
        $this->assertSame(HomeHtml::default(), HomeHtml::sanitize(null));
    }
}
