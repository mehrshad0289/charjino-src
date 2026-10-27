<?php

namespace App\Jobs;

use App\Models\FulfillmentAttempt;
use App\Modules\Fulfillment\FazerOrderOutcome;
use App\Modules\Fulfillment\PollSchedule;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Support\SafeLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshFazerOrder implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 60;

    public function __construct(public readonly int $attemptId)
    {
        $this->onQueue('fulfillment');
    }

    public function uniqueId(): string
    {
        return (string) $this->attemptId;
    }

    public function handle(FazerCardsAdapter $adapter, FazerOrderOutcome $outcome): void
    {
        $attempt = FulfillmentAttempt::query()->find($this->attemptId);
        if (! $attempt || $attempt->isTerminal() || ! $attempt->fazer_order_id) {
            return;
        }
        try {
            $parsed = $adapter->getOrder((string) $attempt->fazer_order_id);
            $outcome->applyParsed($attempt, $parsed, 'poller');
            $attempt->refresh();
            $attempt->poll_count = (int) $attempt->poll_count + 1;
            $attempt->last_polled_at = now();
            if (! $attempt->isTerminal()) {
                $attempt->next_poll_at = PollSchedule::nextAt(
                    $attempt->sending_at ?? $attempt->created_at ?? now(),
                    (int) $attempt->poll_count,
                    $attempt->last_webhook_at,
                );
            }
            $attempt->save();
        } catch (\Throwable $e) {
            SafeLog::warning('fazer.refresh.failed', [
                'attempt_id' => $this->attemptId,
                'code' => $e instanceof \App\Exceptions\ApiException ? $e->errorCode : 'INTERNAL_ERROR',
            ]);
        }
    }
}
