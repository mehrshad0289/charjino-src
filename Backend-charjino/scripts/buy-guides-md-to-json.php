<?php

/**
 * Convert markdown buy-guides (via map.json) into JSON files next to the markdown.
 * Usage: php scripts/buy-guides-md-to-json.php [path=docs/buy-guide/seed]
 */

use App\Modules\BuyGuide\BuyGuideMarkdownParser;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path = $argv[1] ?? 'docs/buy-guide/seed';
$parser = $app->make(BuyGuideMarkdownParser::class);
$command = $app->make(App\Console\Commands\ImportBuyGuidesCommand::class);

$exit = Illuminate\Support\Facades\Artisan::call('buy-guides:import', [
    'path' => $path,
    '--dry-run' => true,
]);

fwrite(STDOUT, Illuminate\Support\Facades\Artisan::output());
exit($exit);
