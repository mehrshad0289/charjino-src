<?php

namespace App\Console\Commands;

use App\Services\Torob\TorobProductQueryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TorobStatsCommand extends Command
{
    protected $signature = 'torob:stats {--explain : چاپ SQL و EXPLAIN فقط‌خواندنی} {--availability : شمارش availability=false با cursor ۱۰۰تایی}';

    protected $description = 'گزارش فقط‌خواندنی تعداد محصولات listable ترب';

    public function handle(TorobProductQueryService $query): int
    {
        $started = hrtime(true);

        if ($this->option('availability')) {
            $counts = $query->availabilityCounts();
            $this->info('listable='.$counts['listable']);
            $this->line('availability_false='.$counts['availability_false']);
        } else {
            $this->info('listable='.$query->listableCount());
            $this->line('availability_false=نامعلوم (با --availability)');
        }

        $this->line('listable_not_indexable=نامعلوم');
        $this->line('دلیل: isIndexableProduct فقط در TypeScript فرانت است (Frontend-charjino/src/lib/seo-rules.ts) و متد PHP ندارد؛ کپی منطق ممنوع است.');

        if ($this->option('explain')) {
            $driver = DB::connection()->getDriverName();
            $prefix = $driver === 'sqlite' ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';
            foreach ($query->explainSqls() as $name => $sql) {
                $this->newLine();
                $this->info($name);
                $this->line($sql);
                try {
                    $rows = DB::select($prefix.$sql);
                    foreach ($rows as $row) {
                        $this->line(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    }
                } catch (\Throwable $e) {
                    $this->warn('EXPLAIN نامعلوم: '.$e->getMessage());
                }
            }
        }

        $this->line('peak_memory_bytes='.memory_get_peak_usage(true));
        $this->line('elapsed_ms='.(int) round((hrtime(true) - $started) / 1e6));

        return self::SUCCESS;
    }
}
