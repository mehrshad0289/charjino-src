<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Models\BuyGuide;
use App\Modules\BuyGuide\BuyGuideMarkdownParser;
use App\Modules\BuyGuide\BuyGuideMediaService;
use App\Modules\BuyGuide\BuyGuideScope;
use App\Modules\BuyGuide\BuyGuideService;
use Illuminate\Console\Command;

class ImportBuyGuidesCommand extends Command
{
    protected $signature = 'buy-guides:import {path=docs/buy-guide/seed} {--publish} {--dry-run}';

    protected $description = 'ایمپورت راهنماهای خرید از JSON یا Markdown';

    public function handle(BuyGuideService $guides, BuyGuideMarkdownParser $parser, BuyGuideMediaService $media): int
    {
        $path = $this->argument('path');
        $root = $this->resolveRoot((string) $path);
        if (! is_dir($root)) {
            $this->error('مسیر پیدا نشد: '.$root);

            return self::FAILURE;
        }
        $mapFile = $root.DIRECTORY_SEPARATOR.'map.json';
        $entries = is_file($mapFile)
            ? $this->fromMap($mapFile, $root)
            : $this->fromDirectory($root);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $rejected = [];
        foreach ($entries as $entry) {
            try {
                $payload = $this->payloadFor($entry, $parser);
                if ($this->option('publish')) {
                    $payload['status'] = 'published';
                }
                if ($this->option('dry-run')) {
                    $this->line('DRY '.$payload['scopeType'].' '.$payload['category'].' '.($payload['subcategory'] ?? ''));
                    $created++;
                    continue;
                }
                $payload = $this->attachImages($payload, $media);
                $key = BuyGuideScope::key(
                    $payload['scopeType'],
                    $payload['category'],
                    (string) ($payload['subcategory'] ?? ''),
                    isset($payload['productId']) ? (int) $payload['productId'] : null,
                );
                $existing = BuyGuide::query()->where('scope_key', $key)->first();
                if ($existing) {
                    $guides->update($existing, $payload, null);
                    $updated++;
                } else {
                    $guides->create($payload, null);
                    $created++;
                }
            } catch (ApiException $e) {
                $skipped++;
                $rejected[] = ($entry['file'] ?? '').' — '.$e->getMessage();
                $this->warn($e->getMessage().' — '.($entry['file'] ?? ''));
            } catch (\Throwable $e) {
                $skipped++;
                $rejected[] = ($entry['file'] ?? '').' — '.$e->getMessage();
                $this->warn($e->getMessage().' — '.($entry['file'] ?? ''));
            }
        }
        $this->info("created={$created} updated={$updated} skipped={$skipped}");
        if ($rejected !== []) {
            $this->newLine();
            $this->warn('rejected:');
            foreach ($rejected as $row) {
                $this->line(' - '.$row);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function payloadFor(array $entry, BuyGuideMarkdownParser $parser): array
    {
        $file = (string) ($entry['abs'] ?? '');
        if (str_ends_with(mb_strtolower($file), '.json')) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (! is_array($decoded)) {
                throw new ApiException('JSON نامعتبر است.', 422);
            }

            return $decoded + array_intersect_key($entry, array_flip(['scopeType', 'category', 'subcategory']));
        }
        $markdown = (string) file_get_contents($file);

        return $parser->parse($markdown, $entry, dirname($file));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function attachImages(array $payload, BuyGuideMediaService $media): array
    {
        $walk = function (&$node) use (&$walk, $media): void {
            if (! is_array($node)) {
                return;
            }
            if (isset($node['image'], $node['_abs']) && is_file((string) $node['_abs'])) {
                try {
                    $row = $media->storeImageFromPath((string) $node['_abs'], (string) ($node['alt'] ?? 'تصویر راهنمای خرید'));
                    $node['media_id'] = $row->id;
                } catch (\Throwable) {
                }
            }
            unset($node['image'], $node['_abs']);
            foreach ($node as &$child) {
                $walk($child);
            }
        };
        $walk($payload['sections']);
        unset($payload['_images']);

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromMap(string $mapFile, string $root): array
    {
        $map = json_decode((string) file_get_contents($mapFile), true);
        if (! is_array($map)) {
            return [];
        }
        $sourceRoot = $root;
        if (! empty($map['sourceRoot'])) {
            $candidate = $this->resolveRoot((string) $map['sourceRoot'], $root);
            if (is_dir($candidate)) {
                $sourceRoot = $candidate;
            }
        }
        $out = [];
        foreach ($map['files'] ?? [] as $row) {
            if (! is_array($row) || empty($row['file'])) {
                continue;
            }
            $abs = $sourceRoot.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $row['file']);
            if (! is_file($abs)) {
                $this->warn('فایل نیست: '.$abs);
                continue;
            }
            $out[] = $row + ['abs' => $abs];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromDirectory(string $root): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['json', 'md'], true) || $file->getBasename() === 'map.json') {
                continue;
            }
            $out[] = ['file' => $file->getFilename(), 'abs' => $file->getPathname()];
        }

        return $out;
    }

    private function resolveRoot(string $path, ?string $relativeTo = null): string
    {
        if ($this->isAbsolute($path)) {
            return $path;
        }
        $base = $relativeTo ?: dirname(base_path());
        $candidate = $base.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (is_dir($candidate) || is_file($candidate)) {
            return $candidate;
        }
        $fromBackend = base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
        if (is_dir($fromBackend) || is_file($fromBackend)) {
            return $fromBackend;
        }

        return $candidate;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }
}
