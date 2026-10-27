<?php

namespace Tests\Unit;

use App\Modules\Catalog\SearchQuery;
use PHPUnit\Framework\TestCase;

class SearchQueryTest extends TestCase
{
    public function test_normalizes_persian_ye_ke_zwnj_and_punctuation(): void
    {
        $this->assertSame(
            'ویلای شمال کیش',
            SearchQuery::normalize("  ويلاي‌شمال!!  كيش "),
        );
    }

    public function test_drops_single_letter_tokens(): void
    {
        $this->assertSame(
            ['هتل', 'یزد'],
            SearchQuery::tokens(SearchQuery::normalize('هتل ی یزد')),
        );
    }

    public function test_required_hits_follow_and_or_rule(): void
    {
        $this->assertSame(1, SearchQuery::requiredHits(1));
        $this->assertSame(2, SearchQuery::requiredHits(2));
        $this->assertSame(2, SearchQuery::requiredHits(3));
        $this->assertSame(2, SearchQuery::requiredHits(5));
    }
}
