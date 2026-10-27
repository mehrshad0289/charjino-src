<?php

namespace App\Modules\Orders;

use App\Exceptions\ApiException;
use App\Models\DeliveredItem;
use App\Models\FulfillmentAttempt;
use App\Models\Order;
use App\Modules\Supplier\FazerCards\FazerCardsRedactor;
use App\Support\SafeLog;
use Illuminate\Support\Facades\DB;

class OrderDeliveryService
{
    /**
     * @param  array<string, mixed>  $parsed
     */
    public function storeFromFazer(FulfillmentAttempt $attempt, array $parsed): string
    {
        $order = $attempt->order;
        if (! $order) {
            return Order::STATUS_FAILED;
        }
        $cards = is_array($parsed['cards'] ?? null) ? $parsed['cards'] : [];
        $keys = is_array($parsed['keys'] ?? null) ? $parsed['keys'] : [];
        $kind = $cards !== [] ? 'gift_code' : ($keys !== [] ? 'game_key' : null);
        $secrets = $kind === 'gift_code' ? $cards : $keys;
        $item = $attempt->item ?? $order->primaryItem();
        $want = max(1, (int) ($item?->quantity ?? $order->quantity));
        $family = (string) ($attempt->fazer_kind ?: $order->fulfillment_type ?: '');
        $expectsSecret = in_array($family, [\App\Modules\Supplier\FazerCards\FazerCardsIds::GIFTCARD, \App\Modules\Supplier\FazerCards\FazerCardsIds::GAMEKEY], true);

        $attempt->fail_reason = $parsed['failReason'] ?? $attempt->fail_reason;
        $attempt->fazer_status = $parsed['status'] ?? $attempt->fazer_status;
        $attempt->charged_usd = $parsed['chargedUsd'] ?? $attempt->charged_usd;
        $redacted = FazerCardsRedactor::scrub(is_array($parsed['raw'] ?? null) ? $parsed['raw'] : []);
        unset($redacted['cards'], $redacted['keys']);
        $attempt->response_redacted = $redacted;
        $attempt->responded_at = now();

        $status = strtolower((string) ($parsed['status'] ?? ''));
        if ($status === 'completed') {
            if ($expectsSecret && ($kind === null || count($secrets) < $want)) {
                if ($kind !== null) {
                    $this->persistSecrets($order, $item?->id, $attempt->id, $kind, $secrets);
                }
                $attempt->status = FulfillmentAttempt::STATUS_COMPLETED;
                $attempt->terminal_at = now();
                $attempt->save();
                $order->needs_review_reason = 'SHORT_DELIVERY';
                $order->save();

                return Order::STATUS_NEEDS_REVIEW;
            }
            if ($kind !== null) {
                $this->persistSecrets($order, $item?->id, $attempt->id, $kind, $secrets);
            }
            $attempt->status = FulfillmentAttempt::STATUS_COMPLETED;
            $attempt->terminal_at = now();
            $attempt->save();

            return Order::STATUS_COMPLETED;
        }

        $attempt->save();
        if (in_array($status, ['failed', 'refund', 'refunded', 'cancelled'], true)) {
            $attempt->status = in_array($status, ['refund', 'refunded'], true)
                ? FulfillmentAttempt::STATUS_REFUND
                : FulfillmentAttempt::STATUS_FAILED;
            $attempt->terminal_at = now();
            $attempt->save();

            return Order::STATUS_FAILED;
        }
        if ($status === 'created') {
            $attempt->status = FulfillmentAttempt::STATUS_CREATED;
            $attempt->save();

            return Order::STATUS_SUBMITTED;
        }
        if ($status === 'processing') {
            $attempt->status = FulfillmentAttempt::STATUS_PROCESSING;
            $attempt->save();

            return Order::STATUS_PROCESSING;
        }

        return Order::STATUS_SUBMITTING;
    }

    /**
     * @param  list<string>  $secrets
     */
    private function persistSecrets(Order $order, ?int $itemId, int $attemptId, string $kind, array $secrets): void
    {
        $appKey = (string) config('app.key');
        foreach (array_values($secrets) as $i => $secret) {
            $secret = (string) $secret;
            $fp = hash_hmac('sha256', $secret, $appKey);
            $exists = DeliveredItem::query()
                ->where('attempt_id', $attemptId)
                ->where('fingerprint', $fp)
                ->exists();
            if ($exists) {
                continue;
            }
            DeliveredItem::query()->create([
                'order_id' => $order->id,
                'order_item_id' => $itemId,
                'attempt_id' => $attemptId,
                'kind' => $kind,
                'position' => $i,
                'secret' => $secret,
                'fingerprint' => $fp,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @return array{items: list<string>, kind: string}
     */
    public function reveal(Order $order): array
    {
        $status = OrderStateMachine::normalize((string) $order->status);
        if (! in_array($status, [Order::STATUS_COMPLETED, Order::STATUS_NEEDS_REVIEW], true)) {
            throw new ApiException('هنوز کدی برای نمایش نیست.', 400, 'ORDER_STATE_INVALID');
        }
        $rows = DeliveredItem::query()
            ->where('order_id', $order->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        if ($rows->isEmpty()) {
            throw new ApiException('هنوز کدی برای نمایش نیست.', 400, 'ORDER_STATE_INVALID');
        }
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                if ($row->first_revealed_at === null) {
                    $row->first_revealed_at = now();
                }
                $row->reveal_count = (int) $row->reveal_count + 1;
                $row->save();
            }
        });
        SafeLog::info('order.reveal', [
            'order_number' => $order->number,
            'count' => $rows->count(),
        ]);

        return [
            'kind' => (string) $rows->first()?->kind,
            'items' => $rows->map(fn (DeliveredItem $row) => (string) $row->secret)->values()->all(),
        ];
    }

    /**
     * @return array{kind: string|null, itemCount: int, revealedOnce: bool, summary: string|null}
     */
    public function publicSummary(Order $order): array
    {
        $count = DeliveredItem::query()->where('order_id', $order->id)->count();
        $revealed = DeliveredItem::query()->where('order_id', $order->id)->whereNotNull('first_revealed_at')->exists();
        $kind = DeliveredItem::query()->where('order_id', $order->id)->value('kind');
        $item = $order->primaryItem();
        $family = (string) ($order->fulfillment_type ?: $item?->fulfillment_type ?: '');
        $summary = $this->summaryText($order, $family);

        return [
            'kind' => $kind,
            'itemCount' => $count,
            'revealedOnce' => $revealed,
            'summary' => $summary,
        ];
    }

    private function summaryText(Order $order, string $family): ?string
    {
        $status = OrderStateMachine::normalize((string) $order->status);
        if ($status !== Order::STATUS_COMPLETED && $status !== Order::STATUS_NEEDS_REVIEW) {
            return null;
        }
        $masked = $this->mask(BuyerRequirements::playerId(is_array($order->requirements) ? $order->requirements : []));

        return match ($family) {
            'giftcard' => 'کدها آماده است.',
            'gamekey' => 'کلیدها آماده است.',
            'steam-topup' => 'کیف پول استیم '.$masked.' شارژ شد.',
            'steam-gift' => 'گیفت از طریق لینک دعوت ارسال شد؛ درخواست دوستی و گیفت را در Steam بپذیرید.',
            'telegram-stars', 'telegram-premium' => 'ستاره یا پرمیوم برای '.$masked.' فعال شد.',
            'topup' => 'شارژ روی حساب '.$masked.' انجام شد.',
            default => null,
        };
    }

    private function mask(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $len = mb_strlen($value);
        if ($len <= 4) {
            return mb_substr($value, 0, 1).'***';
        }

        return mb_substr($value, 0, 2).'***'.mb_substr($value, -2);
    }
}
