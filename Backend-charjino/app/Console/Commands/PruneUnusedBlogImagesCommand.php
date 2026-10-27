<?php

namespace App\Console\Commands;

use App\Modules\Blog\BlogImageService;
use Illuminate\Console\Command;

class PruneUnusedBlogImagesCommand extends Command
{
    protected $signature = 'charjino:prune-blog-images {--hours=24}';

    protected $description = 'Delete unused blog uploads older than the given hours';

    public function handle(BlogImageService $images): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $deleted = $images->pruneUnused($hours);
        $this->info('pruned '.$deleted);

        return self::SUCCESS;
    }
}
