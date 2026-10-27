<?php

namespace App\Console\Commands;

use App\Modules\Codm\CodmRegionCheck;
use Illuminate\Console\Command;

/** Read-only diagnostic: php artisan codm:region-check 7387097367248044034 --variant=US --fresh */
class CodmRegionCheckCommand extends Command
{
    protected $signature = 'codm:region-check {uid} {--variant=* : US CA IN KZ SA (default all)} {--fresh : bypass cache}';

    protected $description = 'Read-only CODM Activision account-region check against the official store validator';

    public function handle(CodmRegionCheck $check): int
    {
        $variants = $this->option('variant') ?: array_keys(CodmRegionCheck::VARIANTS);
        $rows = [];
        foreach ($variants as $v) {
            $r = $check->check((string) $this->argument('uid'), (string) $v, (bool) $this->option('fresh'));
            $rows[] = [$r->variantCountry, $r->status, $r->accountCountry, $r->nickname, $r->shortId, $r->suggestedVariant ?? '-', $r->reason, $r->httpStatus];
            $this->line(strtoupper((string) $v).' RAW: '.json_encode($r->raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $this->table(['variant', 'status', 'accountCountry', 'nickname', 'shortId', 'suggest', 'reason', 'http'], $rows);

        return self::SUCCESS;
    }
}
