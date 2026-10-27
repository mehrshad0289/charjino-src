<?php

namespace App\Modules\Supplier\FazerCards;

/**
 * Snapshot of https://api.fzr.cards/public/docs/openapi.json (FazerCards Public API 3.0.3).
 * Paths are relative to /api/v2. Update when OpenAPI grows; coverage test must stay at zero orphans.
 *
 * @phpstan-type Operation array{0: string, 1: string}
 */
final class FazerCardsOpenApi
{
    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function operations(): array
    {
        return [
            ['GET', 'me'],
            ['GET', 'balance'],
            ['GET', 'transactions'],
            ['GET', 'transactions/{transactionId}'],
            ['GET', 'account/webhook'],
            ['PUT', 'account/webhook'],
            ['DELETE', 'account/webhook'],
            ['POST', 'account/webhook/secret/regenerate'],
            ['POST', 'account/webhook/test'],
            ['GET', 'account/webhook/deliveries'],
            ['GET', 'orders'],
            ['GET', 'orders/{orderId}'],
            ['GET', 'subscription'],
            ['GET', 'subscription/plans'],
            ['GET', 'subscription/activation-quote'],
            ['POST', 'subscription/activate-from-balance'],
            ['PATCH', 'subscription/auto-renew'],
            ['PATCH', 'subscription/plan'],
            ['GET', 'payments/methods'],
            ['POST', 'payments/create'],
            ['GET', 'payments/{paymentId}'],
            ['POST', 'payments/{paymentId}/verify'],
            ['GET', 'steam-topup/rates'],
            ['POST', 'steam-topup/check-login'],
            ['POST', 'steam-topup/order'],
            ['GET', 'telegram/stars'],
            ['GET', 'telegram/premium'],
            ['POST', 'telegram/stars/buy'],
            ['POST', 'telegram/premium/buy'],
            ['GET', 'gamekeys'],
            ['GET', 'gamekeys/keys'],
            ['GET', 'gamekeys/region-restriction'],
            ['POST', 'gamekeys/order'],
            ['GET', 'giftcards'],
            ['GET', 'giftcards/cards'],
            ['POST', 'giftcards/order'],
            ['GET', 'topups'],
            ['GET', 'topups/offers'],
            ['POST', 'topups/order'],
            ['GET', 'topups/validate-id'],
            ['POST', 'topups/validate-id'],
            ['GET', 'manual-services'],
            ['GET', 'manual-services/{manualServiceId}/offers'],
            ['POST', 'manual-services/order'],
            ['GET', 'manual-services/orders/{orderId}/chat'],
            ['POST', 'manual-services/orders/{orderId}/chat'],
            ['POST', 'funpay/login'],
            ['GET', 'funpay/me'],
            ['GET', 'steam-gifts/games'],
            ['GET', 'steam-gifts/games/{appid}'],
            ['POST', 'steam-gifts/order'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function sampleParts(string $template): array
    {
        $filled = preg_replace('/\{[^}]+\}/', 'sample', $template) ?? $template;

        return array_values(array_filter(explode('/', $filled), fn ($part) => $part !== ''));
    }
}
