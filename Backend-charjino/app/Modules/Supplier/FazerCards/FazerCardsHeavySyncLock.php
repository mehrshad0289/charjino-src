<?php

namespace App\Modules\Supplier\FazerCards;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;

final class FazerCardsHeavySyncLock
{
    public const NAME = 'fazercards-heavy-sync';

    public const TTL_SECONDS = 600;

    public const HEARTBEAT_EVERY_SECONDS = 120;

    private float $lastBeatAt;

    private function __construct(private readonly Lock $lock)
    {
        $this->lastBeatAt = microtime(true);
    }

    public static function acquire(): ?self
    {
        $lock = Cache::lock(self::NAME, self::TTL_SECONDS);
        if (! $lock->get()) {
            return null;
        }

        return new self($lock);
    }

    public function owner(): string
    {
        return (string) $this->lock->owner();
    }

    public function stillOwned(): bool
    {
        $method = new ReflectionMethod($this->lock, 'isOwnedByCurrentProcess');

        return (bool) $method->invoke($this->lock);
    }

    public function heartbeat(): bool
    {
        if (! $this->stillOwned()) {
            return false;
        }
        $every = (int) config('charjino.supplier.fazercards_heavy_lock_heartbeat', self::HEARTBEAT_EVERY_SECONDS);
        if ((microtime(true) - $this->lastBeatAt) < max(1, $every)) {
            return true;
        }
        $ttl = (int) config('charjino.supplier.fazercards_heavy_lock_ttl', self::TTL_SECONDS);
        $ok = $this->lock->refresh(max(30, $ttl));
        if ($ok) {
            $this->lastBeatAt = microtime(true);
        }

        return $ok;
    }

    public function release(): bool
    {
        return (bool) $this->lock->release();
    }

    public static function logLost(string $reason = 'heartbeat'): void
    {
        FazerCardsSyncMetrics::lockLost();
        Log::channel('fazercards')->warning('fazercards heavy lock lost', [
            'reason' => $reason,
        ]);
    }
}
