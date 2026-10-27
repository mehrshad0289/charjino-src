<?php

namespace App\Modules\Supplier\FazerCards;

final class FazerOrderParser
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     orderId: string,
     *     status: string,
     *     kind: string|null,
     *     cards: list<string>,
     *     keys: list<string>,
     *     failReason: string|null,
     *     chargedUsd: string|null,
     *     offerName: string|null,
     *     raw: array<string, mixed>
     * }
     */
    public static function parse(array $payload, string $fallbackId = ''): array
    {
        $data = FazerCardsPayload::unwrap($payload);
        $order = is_array($data['order'] ?? null) ? $data['order'] : $data;
        if (! is_array($order)) {
            $order = [];
        }
        $status = strtolower(trim((string) ($order['status'] ?? $data['status'] ?? 'pending')));
        $fail = $order['fail_reason'] ?? $order['failReason'] ?? $data['fail_reason'] ?? $data['failReason'] ?? null;
        $charged = $order['chargedUsd'] ?? $order['charged_usd'] ?? $order['total_usd'] ?? $order['amountUsd'] ?? null;

        return [
            'orderId' => (string) ($order['order_id'] ?? $order['id'] ?? $order['orderId'] ?? $fallbackId),
            'status' => $status !== '' ? $status : 'pending',
            'kind' => self::stringOrNull($order['kind'] ?? $order['type'] ?? $data['kind'] ?? $data['type'] ?? null),
            'cards' => self::stringList($order['cards'] ?? $data['cards'] ?? null),
            'keys' => self::stringList($order['keys'] ?? $data['keys'] ?? null),
            'failReason' => self::stringOrNull($fail),
            'chargedUsd' => $charged !== null && $charged !== '' ? (string) $charged : null,
            'offerName' => self::stringOrNull($order['offer_name'] ?? $order['offerName'] ?? null),
            'raw' => $order,
        ];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (is_scalar($row) && trim((string) $row) !== '') {
                $out[] = (string) $row;
            }
        }

        return $out;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}
