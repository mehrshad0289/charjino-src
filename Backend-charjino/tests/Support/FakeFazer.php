<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;

final class FakeFazer
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function orderCreated(string $id = 'ord-test', string $status = 'created', array $overrides = []): array
    {
        return array_replace_recursive([
            'success' => true,
            'data' => [
                'id' => $id,
                'status' => $status,
                'kind' => 'gift_card',
                'cards' => [],
                'keys' => [],
            ],
        ], $overrides);
    }

    public static function fakeBalance(string $balance = '100.00'): void
    {
        Http::fake([
            'api.fzr.cards/*balance*' => Http::response(['success' => true, 'data' => ['balance' => $balance]], 200),
        ]);
    }
}
