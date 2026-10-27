<?php

namespace App\Console\Commands;

use App\Services\Torob\TorobMappingAudit;
use Illuminate\Console\Command;

class TorobMappingAuditCommand extends Command
{
    protected $signature = 'torob:mapping-audit
        {--write : نوشتن MAPPING-AUDIT.json و MAPPING-STILL-UNKNOWN.md در rollout}
        {--json : خروجی JSON در stdout}';

    protected $description = 'ممیزی معافیت‌ها و شکاف نگاشت (بدون فعال‌سازی)';

    public function handle(TorobMappingAudit $audit): int
    {
        $payload = $audit->run();
        $rolloutDir = (string) config('torob.split_by_sku.rollout_dir', storage_path('app/torob/rollout'));

        if ($this->option('write')) {
            if (! is_dir($rolloutDir)) {
                mkdir($rolloutDir, 0775, true);
            }
            $jsonPath = $rolloutDir.DIRECTORY_SEPARATOR.'MAPPING-AUDIT.json';
            file_put_contents($jsonPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n");
            $mdPath = $rolloutDir.DIRECTORY_SEPARATOR.'MAPPING-STILL-UNKNOWN.md';
            file_put_contents($mdPath, $this->renderStillUnknownMarkdown($payload));
            $this->info('wrote '.$jsonPath);
            $this->info('wrote '.$mdPath);
        }

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}');
        } else {
            foreach ($payload['scopes'] ?? [] as $slug => $scope) {
                $this->line($slug.': examined='.$scope['products_examined']
                    .' eligible='.$scope['eligible_split']
                    .' exempt='.$scope['exempt']
                    .' gaps='.json_encode($scope['gap_counts'] ?? [], JSON_UNESCAPED_UNICODE));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderStillUnknownMarkdown(array $payload): string
    {
        $lines = [
            '# نگاشت‌های هنوز نامشخص (بدون اعمال روی config)',
            '',
            'تولید: `php artisan torob:mapping-audit --write`',
            'قانون: فقط نگاشت **قطعی** در `config/torob_split_copy.php` می‌آید؛ موارد زیر معاف می‌مانند.',
            '',
        ];
        foreach ($payload['scopes'] ?? [] as $slug => $scope) {
            $lines[] = '## '.$slug;
            $lines[] = '';
            $lines[] = '| معیار | مقدار |';
            $lines[] = '|---|---:|';
            $lines[] = '| بررسی‌شده (غیرفعال) | '.($scope['products_examined'] - ($scope['enabled_skipped'] ?? 0)).' |';
            $lines[] = '| واجد split | '.($scope['eligible_split'] ?? 0).' |';
            $lines[] = '| معاف | '.($scope['exempt'] ?? 0).' |';
            $lines[] = '';
            $lines[] = '### شکاف‌ها (تعداد محصول)';
            $lines[] = '';
            foreach ($scope['gap_counts'] ?? [] as $gap => $count) {
                $lines[] = '- `'.$gap.'`: '.$count;
            }
            $lines[] = '';
            $lines[] = '### نمونه محصولات معاف (حداکثر ۵۰ در JSON کامل)';
            $lines[] = '';
            $lines[] = '| id | handle | دلیل | شکاف | برند needle |';
            $lines[] = '|---:|---|---|---|---|';
            foreach (array_slice($scope['exempt_products'] ?? [], 0, 25) as $row) {
                $lines[] = sprintf(
                    '| %d | `%s` | %s | %s | %s |',
                    (int) ($row['id'] ?? 0),
                    (string) ($row['handle'] ?? ''),
                    (string) ($row['reason'] ?? ''),
                    (string) ($row['primary_gap'] ?? ''),
                    (string) ($row['brand_needle'] ?? '—'),
                );
            }
            $lines[] = '';
            $lines[] = '### پیشنهاد نگاشت (نیاز به تأیید شما)';
            $lines[] = '';
            $lines[] = $this->suggestionsForScope($slug, $scope);
            $lines[] = '';
        }
        $gk = $payload['game_keys_split_policy'] ?? [];
        $lines[] = '## Game Keys — آیا split مناسب است؟';
        $lines[] = '';
        $lines[] = ($gk['split_appropriate'] ?? false) ? 'خیر (فعلاً).' : 'خیر.';
        $lines[] = (string) ($gk['reason'] ?? '');
        $lines[] = '';
        $lines[] = 'الگوی پیشنهادی: `'.($gk['proposed_title_pattern'] ?? '').'`';
        $lines[] = '';
        foreach ($gk['samples_if_persian_catalog_title'] ?? [] as $sample) {
            $lines[] = '- '.$sample;
        }
        $lines[] = '';
        $st = $payload['service_topup_split_policy'] ?? [];
        $lines[] = '## Service top-up — الگوی عنوان';
        $lines[] = '';
        $lines[] = '`'.($st['proposed_title_pattern'] ?? '').'`';
        $lines[] = '';
        foreach ($st['samples'] ?? [] as $sample) {
            $lines[] = '- '.$sample;
        }
        $lines[] = '';

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function suggestionsForScope(string $slug, array $scope): string
    {
        if ($slug === 'gift-card') {
            return implode("\n", [
                '- `astropay` → پیشنهاد برند «آستروپی» (ترجمهٔ رسمی نام AstroPay) + کشور از پسوند handle',
                '- `apex-legends-mobile` → پیشنهاد برند «ایپکس لجندز موبایل» (املای فارسی تأیید شود)',
                '- `airalo-voucher` → بدون ریجن در handle؛ تا تعریف ریجن/نوع voucher معاف',
                '- `unknown_amount` → SKU بدون `N_ccy` در offer یا نام؛ نیاز به نمونه SKU یا قانون جدا (مثلاً Nintendo eShop)',
            ]);
        }
        if ($slug === 'game-topup') {
            return implode("\n", [
                '- هر `topup-{game}` → برند فارسی از عنوان فروشگاه یا تأیید شما؛ واحد از `brand_units`',
                '- `unknown_amount` → عدد در نام SKU نیست؛ split تا اصلاح دادهٔ کاتالوگ ممکن نیست',
                '- دیسکورد: واحد «الماس» احتمالاً alias اشتباه؛ پیشنهاد واحد «نیترو» پس از تأیید SKU',
            ]);
        }
        if ($slug === 'game-keys') {
            return implode("\n", [
                '- تا عنوان فارسی بازی در `product.title` نباشد، split غیرفعال بماند',
                '- `unknown_brand` برای GTA/RDR/Sims: نام فارسی بازی در config یا کاتالوگ',
            ]);
        }

        return '—';
    }
}
