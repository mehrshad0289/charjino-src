<?php

use App\Models\Product;
use App\Services\Torob\TorobFeedVerifier;
use App\Services\Torob\TorobProductMapper;
use App\Services\Torob\TorobProductQueryService;
use App\Services\Torob\TorobSplitPolicy;
use App\Services\Torob\TorobSplitReporter;
use App\Services\Torob\TorobV3Cache;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/charjino/vendor/autoload.php';
$app = require '/var/www/charjino/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$ids = '886,884,725,1514,856,1133,971,593,793,869,724,630,855,1122,954,592,788,882,712,609,826,1132,953,581,792,863,715,603,831,1119,946,577,784,874,722,629,850,1134,961,582,791,880,718,602,816,1115,955,589,785,873';
$idList = array_map('intval', explode(',', $ids));

$query = $app->make(TorobProductQueryService::class);
$mapper = $app->make(TorobProductMapper::class);
$policy = $app->make(TorobSplitPolicy::class);
$reporter = $app->make(TorobSplitReporter::class);
$cache = $app->make(TorobV3Cache::class);

$before = $query->listableTotal();
echo 'feed_before='.$before.PHP_EOL;

$enableExit = $kernel->call('torob:split-by-sku', [
    'action' => 'enable',
    '--group' => 'gift-card',
    '--limit' => 50,
    '--ids' => $ids,
    '--force' => true,
    '--json' => true,
]);
$enableOut = $kernel->output();
echo 'enable_exit='.$enableExit.PHP_EOL;
if (preg_match('/enabled=(\d+)/', $enableOut, $m)) {
    echo 'enabled_line='.$m[0].PHP_EOL;
}
if ($enableExit !== 0) {
    echo "ENABLE_FAILED\n";
    echo $enableOut.PHP_EOL;
    exit(1);
}
echo 'enabled_ids_effective='.implode(',', $policy->enabledProductIds()).PHP_EOL;

$kernel->call('torob:split-by-sku', [
    'action' => 'freeze-canonical',
    '--ids' => $ids,
    '--force' => true,
]);
echo 'freeze_out='.trim($kernel->output()).PHP_EOL;

$kernel->call('torob:split-by-sku', [
    'action' => 'flush-cache',
    '--force' => true,
]);
echo 'flush_out='.trim($kernel->output()).PHP_EOL;

$verifyExit = $kernel->call('torob:verify-feed', [
    '--ids' => $ids,
    '--group' => 'telegram',
    '--sample' => '8',
    '--json' => true,
]);
$verifyOut = $kernel->output();
echo 'verify_exit='.$verifyExit.PHP_EOL;
foreach (explode("\n", $verifyOut) as $line) {
    if (str_starts_with($line, 'file=') || str_starts_with($line, 'pass=') || str_starts_with($line, 'failed=') || str_starts_with($line, 'rows=') || str_starts_with($line, 'ok ') || str_starts_with($line, 'FAIL ')) {
        echo $line.PHP_EOL;
    }
}

if ($verifyExit !== 0) {
    $kernel->call('torob:split-by-sku', [
        'action' => 'disable',
        '--ids' => $ids,
        '--force' => true,
    ]);
    echo 'DISABLED_AFTER_VERIFY_FAIL'.PHP_EOL;
    echo trim($kernel->output()).PHP_EOL;
    exit(1);
}

$after = $query->listableTotal();
echo 'feed_after='.$after.PHP_EOL;
echo 'feed_delta='.($after - $before).PHP_EOL;
echo 'feed_multiplier='.($before > 0 ? round($after / $before, 4) : 0).PHP_EOL;

$files = glob(storage_path('app/torob-verify/*.json')) ?: [];
sort($files);
$report = $files === [] ? [] : json_decode((string) file_get_contents((string) end($files)), true);
$checks = is_array($report['checks'] ?? null) ? $report['checks'] : [];
foreach ($checks as $check) {
    $name = (string) ($check['name'] ?? '');
    $pass = ! empty($check['pass']) ? 'yes' : 'no';
    echo 'check_'.$name.'='.$pass.PHP_EOL;
    if ($name === 'prices' && is_array($check['detail'] ?? null)) {
        foreach ($check['detail'] as $row) {
            echo 'price '.$row['handle'].' sku='.($row['sku_id'] ?? '').' feed='.($row['feed_price'] ?? '').' catalog='.($row['catalog_http_price'] ?? '').' storefront='.($row['storefront_http_status'] ?? '').' match='.(! empty($row['match']) ? 'yes' : 'no').PHP_EOL;
        }
    }
    if ($name === 'lookups' && is_array($check['detail']['products'] ?? null)) {
        foreach (array_slice($check['detail']['products'], 0, 5) as $row) {
            echo 'lookup '.$row['handle'].' urls='.($row['by_urls'] ?? '').' uniques='.($row['by_uniques'] ?? '').' combined='.($row['combined'] ?? '').' pass='.(! empty($row['pass']) ? 'yes' : 'no').PHP_EOL;
        }
    }
    if ($name === 'recent_errors' && is_array($check['detail'] ?? null)) {
        echo 'errors_400='.($check['detail']['torob.v3.products.400'] ?? 0).' errors_500='.($check['detail']['torob.v3.products.500'] ?? 0).PHP_EOL;
    }
}

$measured = $reporter->measure($after);
echo 'measure_total='.($measured['total'] ?? '').' max_pages='.($measured['max_pages'] ?? '').PHP_EOL;
foreach ($measured['samples'] ?? [] as $sample) {
    echo $sample['label']
        .' cold_ms='.($sample['cold']['ms'] ?? '')
        .' warm_ms='.($sample['warm']['ms'] ?? '')
        .' cold_rows='.($sample['cold']['rows'] ?? '')
        .' warm_rows='.($sample['warm']['rows'] ?? '')
        .PHP_EOL;
}

$sampleHandles = ['giftcard-google-play-us', 'giftcard-app-store-itunes-us', 'giftcard-rewarble-eur'];
echo "---API_SAMPLES---\n";
foreach ($sampleHandles as $handle) {
    $product = Product::query()->with(['skus', 'catalogGroup'])->where('handle', $handle)->first();
    if (! $product) {
        echo 'missing '.$handle.PHP_EOL;
        continue;
    }
    $rows = $mapper->toRows($product);
    foreach (array_slice($rows, 0, 3) as $row) {
        echo json_encode([
            'handle' => $handle,
            'page_unique' => $row['page_unique'] ?? null,
            'page_url' => $row['page_url'] ?? null,
            'title' => $row['title'] ?? null,
            'current_price' => $row['current_price'] ?? null,
            'availability' => $row['availability'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
    $base = $mapper->pageUrl($handle);
    $all = $query->byUrls([$base]);
    $canonical = $query->byUniques([(string) $product->id]);
    $skuUrl = (string) ($rows[0]['page_url'] ?? $base);
    $one = $query->byUrls([$skuUrl]);
    $invalid = $query->byUrls([$base.'?sku=99999999']);
    echo 'lookup4 '.$handle.' no_sku='.count($all['products']).' unique='.count($canonical['products']).' valid_sku='.count($one['products']).' invalid_sku='.count($invalid['products']).PHP_EOL;
}

$splitCount = 0;
foreach ($idList as $id) {
    $product = Product::query()->with(['skus', 'catalogGroup'])->find($id);
    if ($product && $mapper->splitsBySku($product)) {
        $splitCount++;
    }
}
echo 'split_products_in_batch='.$splitCount.'/'.count($idList).PHP_EOL;
echo 'cache_after='.json_encode($cache->inspect(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
echo 'STAGE1_OK'.PHP_EOL;
