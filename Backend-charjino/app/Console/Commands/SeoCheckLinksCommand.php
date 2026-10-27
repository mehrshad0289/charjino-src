<?php

namespace App\Console\Commands;

use App\Models\BuyGuide;
use App\Models\CatalogGroup;
use App\Modules\Catalog\CatalogUrlLifecycle;
use Illuminate\Console\Command;

class SeoCheckLinksCommand extends Command
{
    protected $signature = 'seo:check-links';

    protected $description = 'لینک‌های داخلی محتوا را با resolve چک می‌کند و موارد خراب را گزارش می‌دهد';

    public function handle(CatalogUrlLifecycle $lifecycle): int
    {
        $broken = [];
        foreach ($this->contentBlobs() as $source => $text) {
            foreach ($this->wikiTargets($text) as $target) {
                if ($this->skipTarget($target)) {
                    continue;
                }
                $path = str_starts_with($target, '/') ? $target : $lifecycle->catalogPath($target);
                $resolved = $lifecycle->resolve($path);
                if (in_array($resolved['status'], ['gone', 'missing'], true)) {
                    $broken[] = $source.' → '.$target.' ('.$resolved['status'].')';
                }
            }
        }

        if ($broken === []) {
            $this->info('seo:check-links ok');

            return self::SUCCESS;
        }

        foreach ($broken as $row) {
            $this->error($row);
        }

        return self::FAILURE;
    }

    /**
     * @return array<string, string>
     */
    private function contentBlobs(): array
    {
        $out = [];
        $landing = base_path('../Frontend-charjino/src/data/category-landing.ts');
        if (is_file($landing)) {
            $out['category-landing.ts'] = (string) file_get_contents($landing);
        }
        foreach (CatalogGroup::query()->get(['slug', 'purchase_guide']) as $group) {
            $out['group:'.$group->slug] = (string) $group->purchase_guide;
        }
        if (class_exists(BuyGuide::class)) {
            foreach (BuyGuide::query()->with('sections')->get() as $guide) {
                $text = (string) ($guide->intro ?? '');
                foreach ($guide->sections as $section) {
                    $text .= "\n".(string) ($section->body ?? $section->markdown ?? $section->content ?? '');
                }
                $out['buy-guide:'.$guide->id] = $text;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function wikiTargets(string $text): array
    {
        preg_match_all('/\[\[([^\]|]+)(?:\|[^\]]+)?\]\]/', $text, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private function skipTarget(string $target): bool
    {
        if (str_starts_with($target, '/catalog/category')) {
            return true;
        }
        if (str_starts_with($target, '/') && ! str_starts_with($target, '/catalog/')) {
            return true;
        }

        return false;
    }
}
