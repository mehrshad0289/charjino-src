<?php

namespace App\Services\Torob;

class TorobSplitBaselineStore
{
    public function get(): int
    {
        return (int) (TorobSplitJsonFile::read($this->path())['feed_rows_before_rollout'] ?? 0);
    }

    /**
     * Capture feed row count once, at the start of the staged rollout.
     */
    public function remember(int $current): int
    {
        $stored = $this->get();
        if ($stored > 0) {
            return $stored;
        }
        TorobSplitJsonFile::write($this->path(), [
            'feed_rows_before_rollout' => $current,
            'captured_at' => now()->toIso8601String(),
        ]);

        return $current;
    }

    public function path(): string
    {
        $configured = trim((string) config('torob.split_by_sku.baseline_path', ''));

        return $configured !== '' ? $configured : storage_path('app/torob/split-baseline.json');
    }
}
