<?php

namespace App\Modules\Fulfillment;

use Illuminate\Support\Carbon;

final class PollSchedule
{
    /**
     * @return list<int>
     */
    public static function seconds(): array
    {
        $raw = (string) config('charjino.orders.poll_schedule', '30,60,120,300,900,1800,3600');
        $out = [];
        foreach (explode(',', $raw) as $part) {
            $n = (int) trim($part);
            if ($n > 0) {
                $out[] = $n;
            }
        }

        return $out !== [] ? $out : [30, 60, 120, 300, 900, 1800, 3600];
    }

    public static function nextAt(Carbon $sendingAt, int $pollCount, ?Carbon $lastWebhookAt = null): Carbon
    {
        $steps = self::seconds();
        $idx = max(0, $pollCount);
        $offset = $steps[min($idx, count($steps) - 1)];
        $at = $sendingAt->copy()->addSeconds($offset);
        if ($lastWebhookAt) {
            $deferred = $lastWebhookAt->copy()->addMinutes(5);
            if ($deferred->gt($at)) {
                $at = $deferred;
            }
        }

        return $at;
    }
}
