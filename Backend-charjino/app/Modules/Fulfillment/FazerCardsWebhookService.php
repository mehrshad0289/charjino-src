<?php

namespace App\Modules\Fulfillment;

use App\Exceptions\ApiException;
use App\Jobs\ProcessFazerWebhookEvent;
use App\Models\WebhookEvent;
use App\Modules\Ops\AdminAlertService;
use App\Modules\Supplier\FazerCards\FazerCardsCredentials;
use App\Support\CustomerError;
use App\Support\SafeLog;
use Illuminate\Database\QueryException;

class FazerCardsWebhookService
{
    public function __construct(
        private readonly FazerCardsCredentials $credentials,
        private readonly AdminAlertService $alerts,
    ) {}

    /**
     * @return array{accepted: bool, duplicate: bool, event: string}
     */
    public function accept(string $rawBody, string $signatureHeader, string $eventHeader = '', ?string $eventIdHeader = null, ?string $deliveryAttemptHeader = null): array
    {
        $secret = $this->credentials->webhookSecret();
        if ($secret === '') {
            $this->alerts->open('WEBHOOK_UNHEALTHY', 'webhook:secret', []);
            throw new ApiException(CustomerError::message('WEBHOOK_NOT_CONFIGURED'), 503, 'WEBHOOK_NOT_CONFIGURED');
        }
        if (! FazerCardsWebhookSignature::matches($rawBody, $signatureHeader, $secret)) {
            SafeLog::warning('fazercards.webhook.bad_signature', ['event' => $eventHeader]);

            throw new ApiException(CustomerError::message('WEBHOOK_INVALID_SIGNATURE'), 401, 'WEBHOOK_INVALID_SIGNATURE');
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            throw new ApiException('بدنهٔ وب‌هوک نامعتبر است.', 400, 'WEBHOOK_INVALID_BODY');
        }

        $event = $eventHeader !== '' ? $eventHeader : (string) ($payload['event'] ?? '');
        $eventId = trim((string) ($payload['event_id'] ?? $eventIdHeader ?? ''));
        if ($eventId === '') {
            $eventId = hash('sha256', $rawBody);
        }
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $fazerOrderId = (string) ($data['order_id'] ?? $payload['order_id'] ?? '');

        try {
            $row = WebhookEvent::query()->create([
                'provider' => 'fazercards',
                'event_id' => $eventId,
                'event' => $event !== '' ? $event : 'unknown',
                'fazer_order_id' => $fazerOrderId !== '' ? $fazerOrderId : null,
                'data_type' => $data['type'] ?? null,
                'data_status' => $data['status'] ?? null,
                'previous_status' => $data['previous_status'] ?? null,
                'delivery_attempt' => is_numeric($deliveryAttemptHeader) ? (int) $deliveryAttemptHeader : null,
                'signature_valid' => true,
                'payload' => $payload,
                'status' => 'received',
                'received_at' => now(),
            ]);
        } catch (QueryException) {
            return ['accepted' => true, 'duplicate' => true, 'event' => $event];
        }

        ProcessFazerWebhookEvent::dispatch($row->id)->onQueue('webhooks')->afterCommit();

        return ['accepted' => true, 'duplicate' => false, 'event' => $event];
    }
}
