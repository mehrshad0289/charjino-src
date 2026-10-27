<?php

namespace App\Modules\Supplier\FazerCards;

final class FazerCardsPayload
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function unwrap(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    public static function listFrom(array $payload, array $keys): array
    {
        $data = self::unwrap($payload);
        foreach ($keys as $key) {
            $hit = $data[$key] ?? $payload[$key] ?? null;
            if (is_array($hit)) {
                $out = [];
                foreach ($hit as $row) {
                    if (is_array($row)) {
                        $out[] = $row;
                    }
                }

                return $out;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function stringFields(array $input): array
    {
        $out = [];
        foreach ($input as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_scalar($value)) {
                $out[(string) $key] = trim((string) $value);
            }
        }

        return $out;
    }
}
