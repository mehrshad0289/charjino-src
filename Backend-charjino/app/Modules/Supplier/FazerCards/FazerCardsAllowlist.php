<?php

namespace App\Modules\Supplier\FazerCards;

final class FazerCardsAllowlist
{
    /** @var list<string> */
    private const GET_EXACT = [
        'me',
        'balance',
        'transactions',
        'orders',
        'subscription',
        'subscription/plans',
        'subscription/activation-quote',
        'payments/methods',
        'steam-topup/rates',
        'steam-gifts/games',
        'telegram/stars',
        'telegram/premium',
        'gamekeys',
        'gamekeys/keys',
        'gamekeys/region-restriction',
        'giftcards',
        'giftcards/cards',
        'topups',
        'topups/offers',
        'topups/validate-id',
        'manual-services',
        'account/webhook',
        'account/webhook/deliveries',
        'funpay/me',
    ];

    /** @var list<string> */
    public const DANGEROUS_POST = [
        'funpay/login',
        'account/webhook/secret/regenerate',
    ];

    /** @var list<string> */
    public const PAID_POST = [
        'topups/order',
        'giftcards/order',
        'gamekeys/order',
        'steam-topup/order',
        'steam-gifts/order',
        'telegram/stars/buy',
        'telegram/premium/buy',
        'manual-services/order',
        'subscription/activate-from-balance',
    ];

    /** Paid POSTs that FazerCards documents as supporting Idempotency-Key. */
    /** @var list<string> */
    public const IDEMPOTENT_POST = [
        'topups/order',
        'giftcards/order',
        'gamekeys/order',
        'steam-topup/order',
        'steam-gifts/order',
        'manual-services/order',
    ];

    /** @var list<string> */
    public const INVOICE_POST = [
        'payments/create',
    ];

    /** @var list<string> */
    public const GATED_PATCH = [
        'subscription/auto-renew',
        'subscription/plan',
    ];

    /**
     * @param  list<string>  $parts
     */
    public static function allows(string $method, array $parts): bool
    {
        $path = implode('/', $parts);
        $method = strtoupper($method);

        if ($method === 'GET') {
            if (in_array($path, self::GET_EXACT, true)) {
                return true;
            }
            if (($parts[0] ?? '') === 'transactions' && count($parts) === 2) {
                return true;
            }
            if (($parts[0] ?? '') === 'orders' && count($parts) === 2) {
                return true;
            }
            if (($parts[0] ?? '') === 'payments' && count($parts) === 2
                && ! in_array($parts[1], ['methods', 'create'], true)) {
                return true;
            }
            if (($parts[0] ?? '') === 'steam-gifts' && ($parts[1] ?? '') === 'games' && count($parts) === 3) {
                return true;
            }
            if (($parts[0] ?? '') === 'manual-services' && count($parts) === 3 && ($parts[2] ?? '') === 'offers') {
                return true;
            }

            return ($parts[0] ?? '') === 'manual-services'
                && ($parts[1] ?? '') === 'orders'
                && ($parts[3] ?? '') === 'chat'
                && count($parts) === 4;
        }

        if ($method === 'POST') {
            if (in_array($path, [
                'topups/validate-id',
                'steam-topup/check-login',
                'account/webhook/test',
                'account/webhook/secret/regenerate',
                'funpay/login',
                ...self::PAID_POST,
                ...self::INVOICE_POST,
            ], true)) {
                return true;
            }
            if (($parts[0] ?? '') === 'payments' && count($parts) === 3 && ($parts[2] ?? '') === 'verify') {
                return true;
            }

            return ($parts[0] ?? '') === 'manual-services'
                && ($parts[1] ?? '') === 'orders'
                && ($parts[3] ?? '') === 'chat'
                && count($parts) === 4;
        }

        if ($method === 'PATCH') {
            return in_array($path, self::GATED_PATCH, true);
        }

        return in_array($method, ['PUT', 'DELETE'], true) && $path === 'account/webhook';
    }

    public static function isPaidPost(string $path): bool
    {
        return in_array($path, self::PAID_POST, true);
    }

    public static function supportsIdempotency(string $path): bool
    {
        return in_array($path, self::IDEMPOTENT_POST, true);
    }

    public static function isInvoicePost(string $path): bool
    {
        return in_array($path, self::INVOICE_POST, true);
    }

    public static function isGatedPatch(string $path): bool
    {
        return in_array($path, self::GATED_PATCH, true);
    }

    public static function isDangerousPost(string $path): bool
    {
        return in_array($path, self::DANGEROUS_POST, true);
    }

    public static function requiresConfirm(string $method, string $path): bool
    {
        $method = strtoupper($method);
        if ($method === 'POST' && (self::isPaidPost($path) || self::isInvoicePost($path) || self::isDangerousPost($path))) {
            return true;
        }
        if ($method === 'PATCH' && self::isGatedPatch($path)) {
            return true;
        }

        return in_array($method, ['PUT', 'DELETE'], true) && $path === 'account/webhook';
    }
}
