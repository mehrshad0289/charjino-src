<?php

namespace Tests\Unit;

use App\Services\Torob\TorobRolloutPlanner;
use Tests\TestCase;

class TorobRolloutPlannerTest extends TestCase
{
    public function test_build_stages_does_not_split_on_category_ratio(): void
    {
        config([
            'torob.split_by_sku.max_batch' => 500,
            'torob.split_by_sku.max_stage_extra_rows' => 1500,
            'torob.split_by_sku.max_feed_multiplier' => 1.5,
        ]);
        $planner = new TorobRolloutPlanner;
        $method = new \ReflectionMethod(TorobRolloutPlanner::class, 'buildStages');
        $method->setAccessible(true);
        $extras = [];
        for ($i = 1; $i <= 10; $i++) {
            $extras[$i] = 20;
        }
        $stages = $method->invoke($planner, $extras, 50615, 50034, 100, 'gift-card-remaining');
        $this->assertCount(1, $stages);
        $this->assertSame(10, $stages[0]['count']);
        $this->assertSame(200, $stages[0]['projected_extra_rows']);
        $this->assertTrue($stages[0]['under_global_1_5_cap']);
        $this->assertTrue($stages[0]['under_stage_product_cap']);
        $this->assertTrue($stages[0]['under_stage_extra_cap']);
        $this->assertTrue($stages[0]['category_ratio_informational_only']);
    }

    public function test_build_stages_splits_when_extra_rows_exceed_stage_cap(): void
    {
        config([
            'torob.split_by_sku.max_batch' => 500,
            'torob.split_by_sku.max_stage_extra_rows' => 1500,
            'torob.split_by_sku.max_feed_multiplier' => 1.5,
        ]);
        $planner = new TorobRolloutPlanner;
        $method = new \ReflectionMethod(TorobRolloutPlanner::class, 'buildStages');
        $method->setAccessible(true);
        $extras = [];
        for ($i = 1; $i <= 20; $i++) {
            $extras[$i] = 100;
        }
        $stages = $method->invoke($planner, $extras, 50615, 50034, 1000, 'gift-card-remaining');
        $this->assertCount(2, $stages);
        $this->assertSame(15, $stages[0]['count']);
        $this->assertSame(1500, $stages[0]['projected_extra_rows']);
        $this->assertSame(5, $stages[1]['count']);
        $this->assertSame(500, $stages[1]['projected_extra_rows']);
        foreach ($stages as $stage) {
            $this->assertLessThanOrEqual(500, $stage['count']);
            $this->assertTrue($stage['under_global_1_5_cap']);
            $this->assertTrue($stage['under_stage_extra_cap']);
        }
    }

    public function test_build_stages_splits_when_product_count_exceeds_500(): void
    {
        config([
            'torob.split_by_sku.max_batch' => 500,
            'torob.split_by_sku.max_stage_extra_rows' => 1500,
            'torob.split_by_sku.max_feed_multiplier' => 1.5,
        ]);
        $planner = new TorobRolloutPlanner;
        $method = new \ReflectionMethod(TorobRolloutPlanner::class, 'buildStages');
        $method->setAccessible(true);
        $extras = [];
        for ($i = 1; $i <= 501; $i++) {
            $extras[$i] = 1;
        }
        $stages = $method->invoke($planner, $extras, 50615, 50034, 1000, 'gift-card-remaining');
        $this->assertCount(2, $stages);
        $this->assertSame(500, $stages[0]['count']);
        $this->assertSame(1, $stages[1]['count']);
    }

    public function test_remaining_ids_drop_no_orderable_sku_products(): void
    {
        $planner = new \ReflectionClass(TorobRolloutPlanner::class);
        $method = $planner->getMethod('emptyScopeState');
        $method->setAccessible(true);
        $instance = new TorobRolloutPlanner;
        $state = $method->invoke($instance, 'Gift cards (remaining)', 'gift-card');
        $this->assertArrayHasKey('no_orderable_sku_ids', $state);
        $this->assertArrayHasKey('exemption_ids', $state);
        $this->assertSame([], $state['no_orderable_sku_ids']);
    }

    public function test_mapping_proposals_collect_unknown_brand_handles(): void
    {
        $planner = new TorobRolloutPlanner;
        $plan = [
            'scopes' => [
                'gift-card-remaining' => [
                    'mapping_gaps' => [
                        ['gap' => 'unknown_brand', 'sample_handles' => ['giftcard-airbnb-de']],
                    ],
                ],
            ],
        ];
        $out = $planner->mappingProposals($plan);
        $this->assertArrayHasKey('airbnb-de', $out['proposals']['brands']);
    }
}
