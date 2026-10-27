<?php

namespace App\Modules\Supplier\FazerCards;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

final class FazerCardsCredentials
{
    public const SETTING_KEY = 'fazercards_api_key_enc';

    public const SETTING_LIVE = 'fazercards_allow_live_order';

    public const SETTING_WEBHOOK = 'fazercards_webhook_secret_enc';

    public function base(): string
    {
        return rtrim((string) config('charjino.supplier.fazercards_base'), '/');
    }

    public function apiKey(): string
    {
        $enc = Setting::getValue(self::SETTING_KEY);
        if (is_string($enc) && $enc !== '') {
            try {
                $plain = Crypt::decryptString($enc);

                return is_string($plain) ? trim($plain) : '';
            } catch (Throwable) {
            }
        }

        return trim((string) config('charjino.supplier.fazercards_key'));
    }

    public function configured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function keyLast4(): ?string
    {
        $key = $this->apiKey();
        if ($key === '') {
            return null;
        }
        $tail = substr($key, -4);

        return $tail !== '' ? $tail : null;
    }

    public function storeKey(string $key): void
    {
        $trimmed = trim($key);
        if ($trimmed === '') {
            Setting::putValue(self::SETTING_KEY, null);

            return;
        }
        Setting::putValue(self::SETTING_KEY, Crypt::encryptString($trimmed));
    }

    public function liveOrderEnabled(): bool
    {
        $stored = Setting::getValue(self::SETTING_LIVE);
        if (is_string($stored) && $stored !== '') {
            return in_array(strtolower($stored), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) config('charjino.supplier.fazercards_allow_live_order');
    }

    public function storeLiveOrder(bool $enabled): void
    {
        Setting::putValue(self::SETTING_LIVE, $enabled ? 'yes' : 'no');
    }

    public function webhookSecret(): string
    {
        $enc = Setting::getValue(self::SETTING_WEBHOOK);
        if (is_string($enc) && $enc !== '') {
            try {
                $plain = Crypt::decryptString($enc);

                return is_string($plain) ? trim($plain) : '';
            } catch (Throwable) {
            }
        }

        return trim((string) config('charjino.supplier.fazercards_webhook_secret'));
    }

    public function webhookConfigured(): bool
    {
        return $this->webhookSecret() !== '';
    }

    public function storeWebhookSecret(?string $secret): void
    {
        $trimmed = is_string($secret) ? trim($secret) : '';
        if ($trimmed === '') {
            Setting::putValue(self::SETTING_WEBHOOK, null);

            return;
        }
        Setting::putValue(self::SETTING_WEBHOOK, Crypt::encryptString($trimmed));
    }
}
