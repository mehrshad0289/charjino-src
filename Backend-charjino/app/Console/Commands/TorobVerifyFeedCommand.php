<?php

namespace App\Console\Commands;

use App\Services\Torob\TorobFeedVerifier;
use App\Services\Torob\TorobSplitJsonFile;
use Illuminate\Console\Command;

class TorobVerifyFeedCommand extends Command
{
    protected $signature = 'torob:verify-feed
        {--ids= : شناسه محصولات جدا با ویرگول}
        {--group=telegram : گروه کوچک برای پیمایش صفحه‌بندی}
        {--sample=8 : تعداد نمونهٔ مقایسه قیمت HTTP}
        {--json : چاپ JSON}';

    protected $description = 'بررسی فقط‌خواندنی فید ترب v3؛ خروجی قبول/رد و JSON در storage/app/torob-verify';

    public function handle(TorobFeedVerifier $verifier): int
    {
        $ids = [];
        foreach (explode(',', (string) $this->option('ids')) as $id) {
            $id = (int) trim($id);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $report = $verifier->verify(
            $ids,
            trim((string) $this->option('group')) ?: null,
            max(1, (int) $this->option('sample')),
        );
        $dir = storage_path('app/torob-verify');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.DIRECTORY_SEPARATOR.now()->format('Ymd-His').'.json';
        TorobSplitJsonFile::write($path, $report);
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}');
        }
        $this->line('file='.$path);
        $this->line('pass='.($report['pass'] ? 'yes' : 'no'));
        $this->line('failed='.($report['failed'] === [] ? '(none)' : implode(',', $report['failed'])));
        $this->line('rows='.$report['row_count'].' elapsed_ms='.$report['elapsed_ms']);
        foreach ($report['checks'] as $check) {
            $this->line(($check['pass'] ? 'ok' : 'FAIL').' '.$check['name']);
        }
        foreach ($report['no_orderable_sku'] ?? [] as $row) {
            $this->line('no_orderable_sku id='.(string) ($row['product_id'] ?? '').' handle='.(string) ($row['handle'] ?? ''));
        }
        foreach ($report['skip_reasons'] ?? [] as $row) {
            $this->line('skip id='.(string) ($row['product_id'] ?? '').' reason='.(string) ($row['reason'] ?? '').' handle='.(string) ($row['handle'] ?? ''));
        }

        return $report['pass'] ? self::SUCCESS : self::FAILURE;
    }
}
