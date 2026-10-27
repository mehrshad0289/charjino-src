<?php

namespace App\Modules\Supplier\Shop2;

final class OrderAttemptStore
{
    /**
     * Persist the supplier order UUID before any HTTP create. No player fields, no secrets.
     *
     * @param  array{itemId?: int, expectedUnitPrice?: string}  $meta
     */
    public static function remember(string $orderId, array $meta = []): void
    {
        $dir = storage_path('app/supplier/attempts');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $safe = preg_replace('/[^A-Za-z0-9\-]/', '', $orderId) ?: 'invalid';
        file_put_contents($dir.'/'.$safe.'.json', json_encode([
            'orderId' => $orderId,
            'itemId' => $meta['itemId'] ?? null,
            'expectedUnitPrice' => $meta['expectedUnitPrice'] ?? null,
            'createdAt' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
