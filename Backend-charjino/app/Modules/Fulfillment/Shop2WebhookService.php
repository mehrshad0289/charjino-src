<?php

namespace App\Modules\Fulfillment;

use App\Exceptions\ApiException;
use App\Jobs\ProcessShop2Webhook;
use App\Models\SupplierWebhookEvent;
use App\Support\CustomerError;
use App\Support\SafeLog;
use Illuminate\Database\QueryException;

class Shop2WebhookService
{
    public function __construct(private readonly FulfillmentService $fulfillment) {}

    /**
     * Verify HMAC on the raw body first, then parse JSON. Returns quickly; heavy work is queued.
     *
     * @return array{accepted: bool, duplicate: bool, event: string}
     */
    public function accept(string $rawBody, string $signatureHeader, string $eventHeader): array
    {
        $secret = (string) config('charjino.supplier.webhook_secret');
        if ($secret === '') {
            throw new ApiException(CustomerError::message('WEBHOOK_NOT_CONFIGURED'), 503, 'WEBHOOK_NOT_CONFIGURED');
        }

        if (! Shop2WebhookSignature::matches($rawBody, $signatureHeader, $secret)) {
            SafeLog::warning('shop2.webhook.bad_signature', ['event' => $eventHeader]);
            throw new ApiException(CustomerError::message('WEBHOOK_INVALID_SIGNATURE'), 401, 'WEBHOOK_INVALID_SIGNATURE');
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            throw new ApiException('بدنهٔ وب‌هوک نامعتبر است.', 400, 'WEBHOOK_INVALID_BODY');
        }

        $event = $eventHeader !== '' ? $eventHeader : (string) ($payload['event'] ?? '');
        $supplierOrderId = (string) (
            $payload['order_id']
            ?? $payload['orderId']
            ?? data_get($payload, 'data.order_id')
            ?? ''
        );

        if ($event === 'webhook.test') {
            $supplierOrderId = $supplierOrderId !== '' ? $supplierOrderId : '00000000-0000-0000-0000-000000000000';
        }

        if ($event === '' || $supplierOrderId === '') {
            throw new ApiException('بدنهٔ وب‌هوک نامعتبر است.', 400, 'WEBHOOK_INVALID_BODY');
        }

        try {
            $row = SupplierWebhookEvent::query()->create([
                'event' => $event,
                'supplier_order_id' => $supplierOrderId,
                'signature_valid' => true,
                'payload' => [
                    'event' => $event,
                    'order_id' => $supplierOrderId,
                    'status' => $payload['status'] ?? null,
                ],
            ]);
        } catch (QueryException) {
            SafeLog::info('shop2.webhook.duplicate', ['event' => $event]);

            return ['accepted' => true, 'duplicate' => true, 'event' => $event];
        }

        ProcessShop2Webhook::dispatch($row->id);

        return ['accepted' => true, 'duplicate' => false, 'event' => $event];
    }

    public function process(int $eventId): void
    {
        $row = SupplierWebhookEvent::query()->find($eventId);
        if (! $row || $row->processed_at) {
            return;
        }

        if ($row->event === 'webhook.test') {
            $row->processed_at = now();
            $row->save();

            return;
        }

        $status = match ($row->event) {
            'order.completed' => 'completed',
            'order.refunded' => 'refunded',
            default => (string) data_get($row->payload, 'status', 'pending'),
        };

        $this->fulfillment->applySupplierStatus($row->supplier_order_id, $status);
        $row->processed_at = now();
        $row->save();
    }
}
