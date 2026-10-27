<?php

namespace App\Models;

use App\Modules\Orders\BuyerRequirements;
use App\Modules\Supplier\FazerCards\FazerCardsCredentials;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_PENDING_PAYMENT = 'awaiting_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_SUBMITTING = 'submitting';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_FULFILLING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DELIVERED = 'completed';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_PARTIAL = 'needs_review';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUND_PENDING = 'refund_pending';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'id',
        'number',
        'user_id',
        'sku_id',
        'product_id',
        'coupon_id',
        'coupon_code',
        'quantity',
        'requirements',
        'player_name',
        'amount_toman',
        'subtotal_toman',
        'discount_toman',
        'cost_usd_snapshot',
        'fx_rate_snapshot',
        'margin_snapshot',
        'status',
        'idempotency_key',
        'body_hash',
        'customer_message',
        'timeline',
        'expires_at',
        'fulfillment_type',
        'hold_reason',
        'hold_until',
        'paid_at',
        'completed_at',
        'failed_at',
        'refunded_at',
        'needs_review_reason',
        'guide_slug',
        'notified_statuses',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount_toman' => 'integer',
            'subtotal_toman' => 'integer',
            'discount_toman' => 'integer',
            'coupon_id' => 'integer',
            'requirements' => 'array',
            'timeline' => 'array',
            'expires_at' => 'datetime',
            'hold_until' => 'datetime',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'notified_statuses' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (! $order->id) {
                $order->id = (string) Str::uuid();
            }
            if (! $order->number) {
                $order->number = self::nextNumber();
            }
        });
    }

    public static function nextNumber(): string
    {
        for ($i = 0; $i < 8; $i++) {
            $candidate = 'CH'.now()->format('ymd').strtoupper(Str::random(6));
            if (! self::query()->where('number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'CH'.now()->format('ymdHis').strtoupper(Str::lower(Str::random(4)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function primaryItem(): ?OrderItem
    {
        return $this->items()->orderBy('id')->first();
    }

    public function fulfillmentAttempts(): HasMany
    {
        return $this->hasMany(FulfillmentAttempt::class);
    }

    public function deliveredItems(): HasMany
    {
        return $this->hasMany(DeliveredItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(OrderTransition::class);
    }

    public function latestAttempt(): ?FulfillmentAttempt
    {
        return $this->fulfillmentAttempts()->latest('id')->first();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function appendEvent(string $type, array $meta = []): void
    {
        $timeline = $this->timeline ?? [];
        $timeline[] = array_filter([
            'type' => $type,
            'at' => now()->toIso8601String(),
            'status' => $this->status,
            'code' => $meta['code'] ?? null,
            'note' => $meta['note'] ?? null,
        ], fn ($value) => $value !== null);
        $this->timeline = $timeline;
    }

    public function isPayable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING_PAYMENT], true)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Public shopper payload — no costUsd, no supplier UUID.
     *
     * @return array<string, mixed>
     */
    public function toCustomer(): array
    {
        $this->loadMissing(['sku', 'product', 'payments']);
        $payment = $this->payments->sortByDesc('id')->first();
        $raw = is_array($payment?->raw) ? $payment->raw : [];
        $psp = is_array($raw['zarinpal'] ?? null) ? $raw['zarinpal'] : (is_array($raw['zibal'] ?? null) ? $raw['zibal'] : []);

        $status = \App\Modules\Orders\OrderStateMachine::normalize((string) $this->status);
        $refund = $this->relationLoaded('refunds')
            ? $this->refunds->sortByDesc('id')->first()
            : $this->refunds()->latest('id')->first();
        $delivery = app(\App\Modules\Orders\OrderDeliveryService::class)->publicSummary($this);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'statusLabelFa' => self::statusLabelFa($status),
            'fulfillmentType' => $this->fulfillment_type ?: $this->sku?->fulfillment_type,
            'amountToman' => $this->amount_toman,
            'subtotalToman' => (int) ($this->subtotal_toman ?: $this->amount_toman),
            'discountToman' => (int) $this->discount_toman,
            'couponCode' => $this->coupon_code,
            'quantity' => $this->quantity,
            'playerName' => $this->player_name,
            'playerId' => $this->ownedPlayerId(),
            'versionLabel' => $this->publicVersionLabel(),
            'requirementsMasked' => $this->maskedRequirements(),
            'skuName' => $this->sku?->name,
            'productTitle' => $this->product?->title,
            'customerMessage' => $this->customer_message,
            'failMessageFa' => in_array($status, [self::STATUS_FAILED, self::STATUS_REFUND_PENDING, self::STATUS_REFUNDED], true)
                ? ($this->customer_message ?: \App\Support\CustomerError::message('FAZER_FAILED'))
                : null,
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'timeline' => $this->publicTimeline(),
            'delivery' => $delivery,
            'refund' => $refund?->toCustomer(),
            'guide' => $this->guidePayload(),
            'pollHintSeconds' => $this->pollHintSeconds($status),
            'dryRun' => $this->isDryRun(),
            'paymentProvider' => $payment?->provider,
            'paymentRef' => $payment?->ref ?: (is_array($psp) ? ($psp['ref_id'] ?? $psp['refNumber'] ?? null) : null),
            'paymentCardMasked' => is_array($psp) ? ($psp['card_pan'] ?? $psp['cardNumber'] ?? null) : null,
        ];
    }

    public static function statusLabelFa(string $status): string
    {
        return match (\App\Modules\Orders\OrderStateMachine::normalize($status)) {
            self::STATUS_DRAFT => 'پیش‌نویس',
            self::STATUS_AWAITING_PAYMENT => 'در انتظار پرداخت',
            self::STATUS_PAID => 'پرداخت شد؛ در صف انجام',
            self::STATUS_SUBMITTING, self::STATUS_SUBMITTED, self::STATUS_PROCESSING => 'در حال انجام',
            self::STATUS_COMPLETED => 'تکمیل شد',
            self::STATUS_NEEDS_REVIEW => 'در حال بررسی توسط پشتیبانی شارژینو',
            self::STATUS_FAILED => 'ناموفق؛ بازگشت وجه در جریان است',
            self::STATUS_REFUND_PENDING => 'در حال بازگشت وجه',
            self::STATUS_REFUNDED => 'وجه بازگشت داده شد',
            self::STATUS_CANCELLED => 'لغو شد',
            self::STATUS_EXPIRED => 'مهلت پرداخت تمام شد',
            default => 'در حال انجام',
        };
    }

    public function isDryRun(): bool
    {
        $attempt = $this->latestAttempt();
        if ($attempt !== null) {
            return (bool) $attempt->dry_run;
        }

        $this->loadMissing('sku');
        if ($this->sku?->isFazerCards()) {
            return ! app(FazerCardsCredentials::class)->liveOrderEnabled();
        }

        return ! (bool) config('charjino.supplier.allow_live_order');
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(): array
    {
        $this->loadMissing(['sku', 'product', 'payments', 'fulfillmentAttempts', 'user', 'items', 'transitions', 'refunds']);
        $fazerIds = $this->fulfillmentAttempts->pluck('fazer_order_id')->filter()->values()->all();
        $attemptIds = $this->fulfillmentAttempts->pluck('id')->all();
        $webhooks = collect();
        if ($fazerIds !== [] || $attemptIds !== []) {
            $webhooks = WebhookEvent::query()
                ->where('provider', 'fazercards')
                ->where(function ($q) use ($fazerIds, $attemptIds) {
                    if ($fazerIds !== []) {
                        $q->whereIn('fazer_order_id', $fazerIds);
                    }
                    if ($attemptIds !== []) {
                        $q->orWhereIn('attempt_id', $attemptIds);
                    }
                })
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        return [
            ...$this->toCustomer(),
            'userId' => $this->user_id,
            'userPhoneNumber' => $this->user?->phone,
            'skuId' => $this->sku_id,
            'productId' => $this->product_id,
            'requirements' => $this->requirements,
            'costUsdSnapshot' => $this->cost_usd_snapshot,
            'fxRateSnapshot' => $this->fx_rate_snapshot,
            'holdReason' => $this->hold_reason,
            'holdUntil' => $this->hold_until?->toIso8601String(),
            'needsReviewReason' => $this->needs_review_reason,
            'paidAt' => $this->paid_at?->toIso8601String(),
            'timeline' => $this->timeline ?? [],
            'items' => $this->items->map(fn (OrderItem $item) => $item->toAdmin())->all(),
            'payments' => $this->payments->map(fn (Payment $p) => $p->toAdmin())->all(),
            'fulfillmentAttempts' => $this->fulfillmentAttempts->map(fn (FulfillmentAttempt $a) => $a->toAdmin())->all(),
            'transitions' => $this->transitions->map(fn (OrderTransition $row) => [
                'from' => $row->from,
                'to' => $row->to,
                'actor' => $row->actor,
                'code' => $row->code,
                'note' => $row->note,
                'createdAt' => $row->created_at?->toIso8601String(),
            ])->all(),
            'webhookEvents' => $webhooks->map(fn (WebhookEvent $row) => $row->toAdmin())->all(),
            'refunds' => $this->refunds->map(fn (Refund $row) => [
                'id' => $row->id,
                'method' => $row->method,
                'status' => $row->status,
                'amountToman' => $row->amount_toman,
                'externalRef' => $row->external_ref,
                'note' => $row->note,
            ])->all(),
        ];
    }

    private function publicVersionLabel(): ?string
    {
        $requirements = is_array($this->requirements) ? $this->requirements : [];
        $version = trim((string) ($requirements['version'] ?? ''));
        if ($version === '') {
            $this->loadMissing(['sku', 'product']);
            $version = (string) ($this->sku?->fazercards_category_id ?: $this->product?->fazercards_category_id ?: '');
        }

        return \App\Modules\Catalog\CodmCatalog::label($version);
    }

    private function ownedPlayerId(): ?string
    {
        $id = BuyerRequirements::playerId(is_array($this->requirements) ? $this->requirements : []);

        return $id !== '' ? $id : null;
    }

    /**
     * @return list<array{key: string, label: string, value: string}>
     */
    private function maskedRequirements(): array
    {
        $out = [];
        foreach (is_array($this->requirements) ? $this->requirements : [] as $key => $value) {
            if (in_array((string) $key, ['id_ack', 'idAck', 'id_ack_at', 'id_unchecked', 'steam_unverified', 'region_ack'], true)) {
                continue;
            }
            if ((string) $key === 'version') {
                $label = \App\Modules\Catalog\CodmCatalog::label((string) $value) ?: (string) $value;
                $out[] = ['key' => 'version', 'label' => 'نسخه', 'value' => $label];

                continue;
            }
            if (in_array((string) $key, ['currency', 'amount'], true)) {
                $out[] = ['key' => (string) $key, 'label' => (string) $key, 'value' => (string) $value];

                continue;
            }
            $text = trim((string) $value);
            $len = mb_strlen($text);
            $masked = $len <= 4 ? mb_substr($text, 0, 1).'***' : mb_substr($text, 0, 2).'***'.mb_substr($text, -2);
            $out[] = [
                'key' => (string) $key,
                'label' => match ((string) $key) {
                    'player_id', 'playerId' => 'شناسهٔ بازیکن',
                    'user_id' => 'شناسهٔ کاربری',
                    'open_id' => 'Open ID',
                    'riot_id' => 'Riot ID',
                    'invite_url' => 'لینک دعوت',
                    'telegram_username', 'username' => 'نام کاربری تلگرام',
                    'steamLogin' => 'Steam login',
                    default => (string) $key,
                },
                'value' => $masked,
            ];
        }

        return $out;
    }

    /**
     * @return array{title: string, url: string}|null
     */
    private function guidePayload(): ?array
    {
        if (! $this->product) {
            return null;
        }
        $guide = app(\App\Modules\BuyGuide\BuyGuideResolver::class)->forProduct($this->product);
        if (! is_array($guide)) {
            return null;
        }
        $title = (string) ($guide['title'] ?? '');
        $url = (string) ($guide['url'] ?? $guide['sharePath'] ?? '');
        if ($title === '' && $url === '') {
            return null;
        }

        return ['title' => $title, 'url' => $url];
    }

    private function pollHintSeconds(string $status): ?int
    {
        if (in_array($status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_REFUNDED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
            self::STATUS_NEEDS_REVIEW,
            self::STATUS_REFUND_PENDING,
        ], true)) {
            return null;
        }
        if (! in_array($status, [self::STATUS_PAID, self::STATUS_SUBMITTING, self::STATUS_SUBMITTED, self::STATUS_PROCESSING, self::STATUS_AWAITING_PAYMENT], true)) {
            return null;
        }
        $start = $this->paid_at ?? $this->created_at;
        if ($start && $start->lt(now()->subMinute())) {
            return 15;
        }

        return 5;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publicTimeline(): array
    {
        $rows = [];
        foreach ($this->timeline ?? [] as $event) {
            if (! is_array($event)) {
                continue;
            }
            $rows[] = [
                'type' => $event['type'] ?? '',
                'at' => $event['at'] ?? null,
                'status' => $event['status'] ?? null,
            ];
        }

        return $rows;
    }
}
