<?php

namespace App\Modules\Supplier\Shop2;

final class Payload
{
    /**
     * @param  array<string, mixed>  $envelope
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    public static function listFrom(array $envelope, array $keys): array
    {
        $payload = is_array($envelope['data'] ?? null) ? $envelope['data'] : $envelope;
        if (! is_array($payload)) {
            return [];
        }

        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_array($payload[$key]) && array_is_list($payload[$key])) {
                return array_values(array_filter($payload[$key], 'is_array'));
            }
        }

        $nested = $payload['data'] ?? null;
        if (is_array($nested)) {
            if (array_is_list($nested)) {
                return array_values(array_filter($nested, 'is_array'));
            }
            foreach ($keys as $key) {
                if (isset($nested[$key]) && is_array($nested[$key]) && array_is_list($nested[$key])) {
                    return array_values(array_filter($nested[$key], 'is_array'));
                }
            }
        }

        return array_is_list($payload) ? array_values(array_filter($payload, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function id(array $row, string ...$keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                return (int) $row[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function name(array $row): string
    {
        foreach (['name', 'title', 'label'] as $key) {
            if (! empty($row[$key]) && is_string($row[$key])) {
                return $row[$key];
            }
        }

        return 'بدون نام';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function usdPrice(array $row): string
    {
        $price = $row['price'] ?? null;
        if (is_array($price)) {
            $value = $price['unit_price'] ?? $price['price'] ?? $price['usd'] ?? null;
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        foreach (['unit_price', 'price_usd', 'usd_price', 'price'] as $key) {
            if (isset($row[$key]) && ! is_array($row[$key]) && $row[$key] !== '') {
                return (string) $row[$key];
            }
        }

        return '0';
    }
}
