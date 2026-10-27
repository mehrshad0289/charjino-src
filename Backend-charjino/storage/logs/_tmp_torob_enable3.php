<?php

use App\Services\Torob\TorobProductQueryService;
use App\Services\Torob\TorobSplitPolicy;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/charjino/vendor/autoload.php';
$app = require '/var/www/charjino/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$ids = '831,946,955';
$before = $app->make(TorobProductQueryService::class)->listableTotal();
echo 'feed_before='.$before.PHP_EOL;

$enableExit = $kernel->call('torob:split-by-sku', [
    'action' => 'enable',
    '--group' => 'gift-card',
    '--limit' => 3,
    '--ids' => $ids,
    '--force' => true,
]);
echo 'enable_exit='.$enableExit.' out='.trim($kernel->output()).PHP_EOL;
if ($enableExit !== 0) {
    exit(1);
}

$kernel->call('torob:split-by-sku', ['action' => 'freeze-canonical', '--ids' => $ids, '--force' => true]);
echo 'freeze='.trim($kernel->output()).PHP_EOL;
$kernel->call('torob:split-by-sku', ['action' => 'flush-cache', '--force' => true]);
echo 'flush='.trim($kernel->output()).PHP_EOL;

$verifyExit = $kernel->call('torob:verify-feed', [
    '--ids' => $ids,
    '--group' => 'telegram',
    '--sample' => '3',
]);
echo 'verify_exit='.$verifyExit.PHP_EOL;
echo $kernel->output();
if ($verifyExit !== 0) {
    $kernel->call('torob:split-by-sku', ['action' => 'disable', '--ids' => $ids, '--force' => true]);
    echo "DISABLED_AFTER_VERIFY_FAIL\n";
    exit(1);
}

echo 'enabled_count='.count($app->make(TorobSplitPolicy::class)->enabledProductIds()).PHP_EOL;
echo 'ENABLE3_OK'.PHP_EOL;
