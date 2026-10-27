<?php

use App\Models\Product;
use App\Services\Torob\TorobProductMapper;
use App\Services\Torob\TorobProductQueryService;
use App\Services\Torob\TorobSplitPolicy;
use App\Services\Torob\TorobSplitReporter;
use App\Services\Torob\TorobSplitRuntimeStore;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/charjino/vendor/autoload.php';
$app = require '/var/www/charjino/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$query = $app->make(TorobProductQueryService::class);
$mapper = $app->make(TorobProductMapper::class);
$policy = $app->make(TorobSplitPolicy::class);
$runtime = $app->make(TorobSplitRuntimeStore::class);
$reporter = $app->make(TorobSplitReporter::class);

$ids = [831, 946, 955];
echo 'runtime_ids='.implode(',', $runtime->productIds()).PHP_EOL;
echo 'enabled_count='.count($policy->enabledProductIds()).PHP_EOL;
echo 'feed_rows='.$query->listableTotal().PHP_EOL;

foreach ($ids as $id) {
    $product = Product::query()->with(['skus', 'catalogGroup'])->find($id);
    if (! $product) {
        echo "missing {$id}\n";
        continue;
    }
    $decision = $policy->decide($product);
    $copy = $app->make(App\Services\Torob\TorobFeedCatalogCopy::class);
    $gaps = [];
    foreach ($product->skus as $sku) {
        $gap = $copy->mappingGap($product, $sku);
        if ($gap !== '') {
            $gaps[] = $sku->id.':'.$sku->name.':'.$gap;
        }
    }
    echo 'skip '.$id.' '.$product->handle.' split='.(int) $decision->split.' reason='.(string) $decision->reason.' rows='.count($mapper->toRows($product)).' gaps='.implode(' | ', array_slice($gaps, 0, 8)).PHP_EOL;
}

foreach (['giftcard-google-play-us', 'giftcard-rewarble-eur', 'giftcard-app-store-itunes-us'] as $handle) {
    $product = Product::query()->with(['skus', 'catalogGroup'])->where('handle', $handle)->first();
    $base = $mapper->pageUrl($handle);
    $all = $query->byUrls([$base]);
    $canonical = $query->byUniques([(string) $product->id]);
    $skuUrl = $mapper->toRows($product)[0]['page_url'] ?? $base;
    $one = $query->byUrls([$skuUrl]);
    $invalid = $query->byUrls([$base.'?sku=99999999']);
    echo 'fresh_lookup '.$handle.' split='.(int) $mapper->splitsBySku($product).' rows='.count($mapper->toRows($product)).' no_sku='.count($all['products']).' unique='.count($canonical['products']).' valid_sku='.count($one['products']).' invalid_sku='.count($invalid['products']).PHP_EOL;
}

$measured = $reporter->measure();
echo 'measure_total='.$measured['total'].' max_pages='.$measured['max_pages'].PHP_EOL;
foreach ($measured['samples'] as $sample) {
    echo $sample['label'].' cold_ms='.$sample['cold']['ms'].' warm_ms='.$sample['warm']['ms'].' rows='.$sample['cold']['rows'].PHP_EOL;
}
