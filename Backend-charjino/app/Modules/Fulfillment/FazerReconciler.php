<?php

namespace App\Modules\Fulfillment;

use App\Exceptions\ApiException;
use App\Jobs\RefreshFazerOrder;
use App\Models\FulfillmentAttempt;
use App\Models\WebhookEvent;
use App\Modules\Ops\AdminAlertService;
use App\Modules\Orders\OrderStateMachine;
use App\Modules\Supplier\FazerCards\FazerCardsAdapter;
use App\Modules\Supplier\FazerCards\FazerCardsClient;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use App\Modules\Supplier\FazerCards\FazerCardsPayload;
use App\Modules\Supplier\FazerCards\FazerOrderParser;

class FazerReconciler
{
    public function __construct(
        private readonly FazerCardsAdapter $adapter,
        private readonly FazerCardsClient $client,
        private readonly FazerSubmitter $submitter,
        private readonly FazerOrderOutcome $outcome,
        private readonly OrderStateMachine $states,
        private readonly AdminAlertService $alerts,
    ) {}

    public function reconcile(FulfillmentAttempt $attempt): void
    {
        $attempt->loadMissing(['order', 'item']);
        if ($attempt->fazer_order_id) {
            RefreshFazerOrder::dispatchSync($attempt->id);

            return;
        }
        $ageDays = $attempt->sending_at?->diffInDays(now()) ?? 0;
        if ($attempt->idempotent && $ageDays < 6 && $attempt->item) {
            try {
                $parsed = $this->submitter->send($attempt, $attempt->item);
                $this->outcome->applyParsed($attempt, $parsed, 'reconciler');

                return;
            } catch (ApiException $e) {
                if ($e->status === 429) {
                    $this->outcome->applyException($attempt, $e);

                    return;
                }
            }
        }

        $candidates = $this->searchCandidates($attempt);
        if (count($candidates) === 1) {
            $attempt->fazer_order_id = $candidates[0];
            $attempt->save();
            RefreshFazerOrder::dispatchSync($attempt->id);

            return;
        }
        $attempt->reconcile_count = (int) $attempt->reconcile_count + 1;
        $attempt->save();
        $timeout = (int) config('charjino.orders.unknown_timeout_minutes', 30);
        $since = $attempt->sending_at ?? $attempt->unknown_since ?? $attempt->created_at;
        if ($since && $since->lte(now()->subMinutes($timeout)) && (int) $attempt->reconcile_count >= 3) {
            $order = $attempt->order;
            if ($order) {
                $order->needs_review_reason = count($candidates) > 1 ? 'UNKNOWN_AMBIGUOUS' : 'UNKNOWN_NOT_FOUND';
                $this->states->transition($order, Order::STATUS_NEEDS_REVIEW, 'reconciler', $order->needs_review_reason);
                $this->alerts->open('UNKNOWN_ATTEMPT', 'unknown:'.$attempt->id, [
                    'order_number' => $order->number,
                ]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function searchCandidates(FulfillmentAttempt $attempt): array
    {
        $ids = [];
        $maxPages = (int) config('charjino.orders.reconcile_max_pages', 5);
        $windowStart = ($attempt->sending_at ?? now())->copy()->subMinute();
        $windowEnd = ($attempt->sending_at ?? now())->copy()->addMinutes(10);
        $stopBefore = ($attempt->sending_at ?? now())->copy()->subMinutes(2);
        $kind = (string) $attempt->fazer_kind;
        for ($page = 1; $page <= $maxPages; $page++) {
            try {
                $payload = $this->client->listOrders(['page' => $page, 'limit' => 100]);
            } catch (ApiException) {
                break;
            }
            $items = FazerCardsPayload::listFrom($payload['data'], ['items', 'orders']);
            if ($items === []) {
                break;
            }
            $stop = false;
            foreach ($items as $row) {
                $parsed = FazerOrderParser::parse($row);
                $ts = $row['createdAt'] ?? $row['created_at'] ?? null;
                $created = is_string($ts) ? \Illuminate\Support\Carbon::parse($ts) : null;
                if ($created && $created->lt($stopBefore)) {
                    $stop = true;
                    break;
                }
                $remoteKind = (string) ($parsed['kind'] ?? '');
                if ($remoteKind !== '' && $kind !== '' && ! $this->kindMatch($kind, $remoteKind)) {
                    continue;
                }
                $id = $parsed['orderId'];
                if ($id === '' || FulfillmentAttempt::query()->where('fazer_order_id', $id)->where('id', '!=', $attempt->id)->exists()) {
                    continue;
                }
                if ($created && $created->between($windowStart, $windowEnd)) {
                    $ids[] = $id;
                }
            }
            if ($stop) {
                break;
            }
        }
        $fromHooks = WebhookEvent::query()
            ->where('provider', 'fazercards')
            ->where('event', 'order.created')
            ->where('status', 'unmatched')
            ->whereBetween('received_at', [$windowStart, $windowEnd])
            ->pluck('fazer_order_id')
            ->filter()
            ->all();
        foreach ($fromHooks as $id) {
            if (! FulfillmentAttempt::query()->where('fazer_order_id', $id)->exists()) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function kindMatch(string $local, string $remote): bool
    {
        $local = str_replace('-', '_', strtolower($local));
        $remote = str_replace('-', '_', strtolower($remote));

        return $local === $remote
            || str_contains($remote, str_replace('_', '', $local))
            || ($local === FazerCardsIds::GIFTCARD && str_contains($remote, 'gift'));
    }
}
