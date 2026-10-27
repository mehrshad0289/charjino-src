<?php

namespace App\Console\Commands;

use App\Modules\Catalog\ProductDuplicateScanner;
use App\Modules\Ops\AdminAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FindDuplicateProductsCommand extends Command
{
    protected $signature = 'products:find-duplicates {--alert : هشدار به ادمین اگر تکراری پیدا شد} {--json : خروجی JSON}';

    protected $description = 'محصولات فعال با کلید نرمال‌شده، عنوان یا متای تکراری را گزارش می‌کند';

    public function handle(ProductDuplicateScanner $scanner, AdminAlertService $alerts): int
    {
        $report = $scanner->report();
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->printReport($report);
        }

        if ($this->option('alert') && ($report['groups'] !== [] || $report['titles'] !== [] || $report['metas'] !== [])) {
            $summary = $this->summary($report);
            Log::warning('catalog.duplicate_products', ['summary' => $summary, 'report' => $report]);
            $alerts->open('catalog.duplicate_products', 'catalog.duplicate_products', [
                'summary' => $summary,
                'groups' => count($report['groups']),
                'titles' => count($report['titles']),
                'metas' => count($report['metas']),
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{groups: list<array<string, mixed>>, titles: list<array<string, mixed>>, metas: list<array<string, mixed>>}  $report
     */
    private function printReport(array $report): void
    {
        $this->info('== normalized-key groups ==');
        if ($report['groups'] === []) {
            $this->line('none');
        }
        foreach ($report['groups'] as $group) {
            $this->newLine();
            $this->line('key='.$group['kind'].'|'.$group['region'].'|'.$group['key']);
            $this->table(
                ['id', 'slug', 'created_at', 'shop2_category_id', 'fazercards_category_id', 'orders'],
                array_map(static fn (array $row): array => [
                    $row['id'],
                    $row['slug'],
                    $row['created_at'],
                    $row['shop2_category_id'] ?? '',
                    $row['fazercards_category_id'] ?? '',
                    $row['orders'],
                ], $group['products'])
            );
        }

        $this->newLine();
        $this->info('== duplicate titles ==');
        if ($report['titles'] === []) {
            $this->line('none');
        }
        foreach ($report['titles'] as $group) {
            $this->line('title='.$group['title']);
            foreach ($group['products'] as $row) {
                $this->line('  id='.$row['id'].' slug='.$row['slug']);
            }
        }

        $this->newLine();
        $this->info('== duplicate meta descriptions ==');
        if ($report['metas'] === []) {
            $this->line('none');
        }
        foreach ($report['metas'] as $group) {
            $this->line('meta='.mb_substr((string) $group['meta'], 0, 120));
            foreach ($group['products'] as $row) {
                $this->line('  id='.$row['id'].' slug='.$row['slug']);
            }
        }
    }

    /**
     * @param  array{groups: list<mixed>, titles: list<mixed>, metas: list<mixed>}  $report
     */
    private function summary(array $report): string
    {
        return 'تکراری کاتالوگ: کلید='.count($report['groups'])
            .' عنوان='.count($report['titles'])
            .' متا='.count($report['metas']);
    }
}
