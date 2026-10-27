<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentAttempt extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_CREATED = 'created';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_REFUND = 'refund';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNKNOWN = 'unknown';

    public const STATUS_ABANDONED = 'abandoned';

    /** @var list<string> */
    public const ACTIVE = ['queued', 'sending', 'created', 'processing', 'unknown'];

    /** @var list<string> */
    public const TERMINAL = ['completed', 'failed', 'refund', 'refunded', 'abandoned'];

    protected $fillable = [
        'order_id',
        'order_item_id',
        'attempt_no',
        'idempotency_key',
        'idempotent',
        'fazer_order_id',
        'fazer_kind',
        'fazer_status',
        'http_status',
        'fail_reason',
        'request_redacted',
        'response_redacted',
        'charged_usd',
        'sending_at',
        'responded_at',
        'terminal_at',
        'unknown_since',
        'reconcile_count',
        'next_poll_at',
        'last_webhook_at',
        'resolved_by_admin_id',
        'resolution_note',
        'supplier',
        'supplier_order_id',
        'expected_unit_price',
        'status',
        'error_code',
        'dry_run',
        'poll_count',
        'last_polled_at',
        'submitted_at',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'poll_count' => 'integer',
            'raw' => 'array',
            'idempotent' => 'boolean',
            'request_redacted' => 'array',
            'response_redacted' => 'array',
            'last_polled_at' => 'datetime',
            'submitted_at' => 'datetime',
            'sending_at' => 'datetime',
            'responded_at' => 'datetime',
            'terminal_at' => 'datetime',
            'unknown_since' => 'datetime',
            'next_poll_at' => 'datetime',
            'last_webhook_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function isActive(): bool
    {
        return in_array((string) $this->status, self::ACTIVE, true);
    }

    public function isTerminal(): bool
    {
        return in_array((string) $this->status, self::TERMINAL, true);
    }

    public function hasSupplierOrder(): bool
    {
        $id = trim((string) $this->fazer_order_id);

        return $id !== '' && ! str_starts_with($id, 'cj-');
    }

    public function neverSentToFazer(): bool
    {
        return $this->status === self::STATUS_QUEUED
            && $this->sending_at === null
            && ! $this->hasSupplierOrder();
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(): array
    {
        $key = (string) $this->idempotency_key;

        return [
            'id' => $this->id,
            'attemptNo' => $this->attempt_no,
            'supplier' => $this->supplier,
            'supplierOrderId' => $this->supplier_order_id,
            'fazerOrderId' => $this->fazer_order_id,
            'fazerKind' => $this->fazer_kind,
            'fazerStatus' => $this->fazer_status,
            'httpStatus' => $this->http_status,
            'failReason' => $this->fail_reason,
            'idempotencyKeyShort' => $key !== '' ? mb_substr($key, 0, 12).'…' : null,
            'idempotent' => (bool) $this->idempotent,
            'expectedUnitPrice' => $this->expected_unit_price,
            'chargedUsd' => $this->charged_usd !== null ? (string) $this->charged_usd : null,
            'status' => $this->status,
            'errorCode' => $this->error_code,
            'dryRun' => $this->dry_run,
            'pollCount' => $this->poll_count,
            'reconcileCount' => $this->reconcile_count,
            'nextPollAt' => $this->next_poll_at?->toIso8601String(),
            'lastPolledAt' => $this->last_polled_at?->toIso8601String(),
            'sendingAt' => $this->sending_at?->toIso8601String(),
            'respondedAt' => $this->responded_at?->toIso8601String(),
            'unknownSince' => $this->unknown_since?->toIso8601String(),
            'lastWebhookAt' => $this->last_webhook_at?->toIso8601String(),
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'requestRedacted' => $this->request_redacted,
            'responseRedacted' => $this->response_redacted,
            'resolutionNote' => $this->resolution_note,
            'raw' => $this->safeRaw(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeRaw(): ?array
    {
        $raw = $this->raw;
        if (! is_array($raw)) {
            return null;
        }
        unset($raw['requirements'], $raw['authorization'], $raw['apiKey']);

        return $raw;
    }
}
