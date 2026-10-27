<?php

namespace App\Jobs\SmartSearch;

use App\Modules\SmartSearch\SearchIndexWriteRegistry;
use App\Modules\SmartSearch\SmartSearchSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncProductSearchDocument implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public int $uniqueFor = 30;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 120];

    public function __construct(
        public readonly ?int $productId = null,
        public readonly ?string $handle = null,
    ) {
        $this->onQueue((string) config('smart-search.queue', 'search'));
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'product:'.($this->productId ?? $this->handle ?? 'unknown');
    }

    public function handle(): void
    {
        if (SearchIndexWriteRegistry::barrier()) {
            $this->release(5);

            return;
        }
        if (! SmartSearchSyncService::writesAreEnabled()) {
            return;
        }
        $ids = [];
        if ($this->productId) {
            $ids[] = $this->productId;
        } elseif ($this->handle) {
            $found = \App\Models\Product::query()->where('handle', $this->handle)->value('id');
            if ($found) {
                $ids[] = (int) $found;
            } else {
                SmartSearchSyncService::make()->deleteProductHandles([$this->handle]);

                return;
            }
        }
        SmartSearchSyncService::make()->syncProducts($ids);
    }

    public function failed(?Throwable $e): void
    {
        \App\Support\SafeLog::warning('smart_search.job_failed', [
            'job' => 'product',
            'productId' => $this->productId,
        ]);
    }
}
