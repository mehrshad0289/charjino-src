<?php

namespace Tests\Unit;

use App\Modules\SmartSearch\KeyboardLayoutTranscoder;
use App\Modules\SmartSearch\MatchingStrategyResolver;
use App\Modules\SmartSearch\MeiliFilter;
use App\Modules\SmartSearch\PackageLabelMatcher;
use App\Modules\SmartSearch\PersianTextNormalizer;
use App\Modules\SmartSearch\RegionParser;
use PHPUnit\Framework\TestCase;

class SmartSearchHelpersTest extends TestCase
{
    public function test_persian_normalizer_digits_and_yeh(): void
    {
        $this->assertSame('پابجی 60 یوسی', PersianTextNormalizer::normalize('پابجي ۶۰ يوسي'));
    }

    public function test_keyboard_transcoder_example(): void
    {
        $this->assertSame('خب الان', KeyboardLayoutTranscoder::toPersian('of hghk'));
        $this->assertSame('کالاف', KeyboardLayoutTranscoder::toPersian(';hght'));
        $this->assertTrue(KeyboardLayoutTranscoder::shouldPreferMapped('of hghk'));
        $this->assertTrue(KeyboardLayoutTranscoder::shouldPreferMapped(';hght'));
        $this->assertFalse(KeyboardLayoutTranscoder::shouldPreferMapped('pubg'));
        $this->assertFalse(KeyboardLayoutTranscoder::shouldPreferMapped('GTA'));
    }

    public function test_matching_strategy(): void
    {
        $this->assertSame('all', MatchingStrategyResolver::forQuery('پابجی 60'));
        $this->assertSame('last', MatchingStrategyResolver::forQuery('پابجی'));
    }

    public function test_region_parser(): void
    {
        $this->assertContains('global', RegionParser::parse('Global region'));
        $this->assertNotContains('us', RegionParser::parse('status-ready-123'));
    }

    public function test_package_label_matcher(): void
    {
        $this->assertSame('60 UC', PackageLabelMatcher::match('پابجی 60 uc', ['60 UC', '325 UC']));
        $this->assertNull(PackageLabelMatcher::match('پابجی', ['60 UC']));
    }

    public function test_meili_filter_rejects_raw_injection(): void
    {
        $this->assertNull(MeiliFilter::equals('group_slug', 'gift" OR 1=1'));
        $this->assertSame('group_slug = "gift-card"', MeiliFilter::equals('group_slug', 'gift-card'));
        $this->assertSame(
            'group_slug NOT IN ["game-topup", "gift-card", "game-keys", "steam-gifts"]',
            MeiliFilter::notIn('group_slug', ['game-topup', 'gift-card', 'game-keys', 'steam-gifts']),
        );
    }
}
