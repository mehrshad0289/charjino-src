<?php

namespace Tests\Unit;

use App\Jobs\FulfillPaidOrder;
use App\Jobs\ProcessFazerCardsWebhook;
use App\Jobs\ProcessShop2Webhook;
use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\SearchTag;
use App\Models\Sku;
use Laravel\Scout\Searchable;
use Tests\TestCase;

class SearchQueueArchitectureTest extends TestCase
{
    public function test_scout_queue_targets_search_not_default(): void
    {
        $queue = config('scout.queue');

        $this->assertIsArray($queue);
        $this->assertSame('search', $queue['queue']);
        $this->assertSame('sync', $queue['connection']);
        $this->assertSame('search', config('charjino.smart_search.queue'));
    }

    public function test_order_payment_and_webhook_jobs_stay_on_default_queue(): void
    {
        $this->assertSame('default', (new FulfillPaidOrder('order-1'))->queue);
        $this->assertSame('default', (new ProcessShop2Webhook(1))->queue);
        $this->assertSame('default', (new ProcessFazerCardsWebhook(1))->queue);
    }

    public function test_searchable_models_are_prepared_but_indexing_is_off(): void
    {
        $this->assertTrue(in_array(config('scout.driver'), [null, 'null'], true));
        $this->assertSame('database', config('charjino.smart_search.driver'));
        $this->assertFalse(config('charjino.smart_search.scout_sync'));

        $product = new Product;
        $product->id = 1;
        $group = new CatalogGroup;
        $group->id = 1;
        $tag = new SearchTag;
        $tag->id = 1;

        $this->assertContains(Searchable::class, class_uses_recursive($product));
        $this->assertContains(Searchable::class, class_uses_recursive($group));
        $this->assertContains(Searchable::class, class_uses_recursive($tag));
        $this->assertNotContains(Searchable::class, class_uses_recursive(new Sku));

        $this->assertFalse($product->shouldBeSearchable());
        $this->assertFalse($group->shouldBeSearchable());
        $this->assertFalse($tag->shouldBeSearchable());

        $this->assertSame('charjino_test_products', $product->searchableAs());
        $this->assertSame('charjino_test_taxonomies', $group->searchableAs());
        $this->assertSame('charjino_test_brands', $tag->searchableAs());

        $doc = $product->toSearchableArray();
        $this->assertSame(['id' => '1'], $doc);
        $this->assertArrayNotHasKey('price', $doc);
        $this->assertArrayNotHasKey('image_url', $doc);
        $this->assertArrayNotHasKey('thumbnail', $doc);
    }
}
