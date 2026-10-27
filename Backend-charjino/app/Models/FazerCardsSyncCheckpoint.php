<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class FazerCardsSyncCheckpoint extends Model
{
    public const WEEKLY_STEAM = 'steam-gifts-weekly';

    protected $table = 'fazercards_sync_checkpoints';

    protected $fillable = [
        'name',
        'period',
        'last_processed_id',
        'last_index',
        'status',
    ];

    public static function weeklyPeriod(?Carbon $at = null): string
    {
        $at = ($at ?? now())->copy()->timezone('Asia/Tehran')->startOfDay();
        if ((int) $at->dayOfWeek !== Carbon::THURSDAY) {
            $at = $at->previous(Carbon::THURSDAY);
        }

        return $at->toDateString();
    }

    public static function weekly(): ?self
    {
        return self::query()
            ->where('name', self::WEEKLY_STEAM)
            ->where('period', self::weeklyPeriod())
            ->first();
    }

    public static function weeklyRunning(): bool
    {
        $row = self::weekly();

        return $row !== null && $row->status === 'running';
    }

    public static function shouldStartWeekly(): bool
    {
        $row = self::weekly();
        if ($row && $row->status === 'running') {
            return true;
        }
        if ($row && $row->status === 'done') {
            return false;
        }

        return now()->timezone('Asia/Tehran')->isThursday();
    }

    public static function saveWeekly(int $lastIndex, ?string $lastProcessedId): void
    {
        self::query()->updateOrCreate(
            [
                'name' => self::WEEKLY_STEAM,
                'period' => self::weeklyPeriod(),
            ],
            [
                'last_index' => max(0, $lastIndex),
                'last_processed_id' => $lastProcessedId,
                'status' => 'running',
            ]
        );
    }

    public static function clearWeekly(): void
    {
        self::query()
            ->where('name', self::WEEKLY_STEAM)
            ->where('period', self::weeklyPeriod())
            ->delete();
    }
}
