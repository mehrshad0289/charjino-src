<?php

namespace App\Modules\Catalog;

use App\Exceptions\ApiException;
use App\Modules\Catalog\BuyerIdentity;
use App\Modules\Supplier\Shop2\ForbiddenBuyerField;

final class BuyerInputFields
{
    /**
     * @param  list<mixed>|null  $fields
     * @return list<array{name: string, label: string, type: string, required: bool, options: mixed}>
     */
    public static function sanitize(?array $fields, bool $strict = false): array
    {
        $out = [];
        foreach ($fields ?? [] as $field) {
            if (is_string($field) || is_int($field)) {
                $field = ['name' => (string) $field, 'required' => true];
            }
            if (! is_array($field)) {
                continue;
            }
            $name = trim((string) ($field['name'] ?? $field['field_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            if (ForbiddenBuyerField::matches($name)) {
                if ($strict) {
                    throw new ApiException('فقط شناسهٔ بازیکن لازم است. رمز بازی را وارد نکن.', 400, 'FORBIDDEN_BUYER_FIELD');
                }

                continue;
            }

            $label = trim((string) ($field['label'] ?? $field['title'] ?? ''));

            $out[] = [
                'name' => $name,
                'label' => $label !== '' ? $label : self::defaultLabel($name),
                'type' => trim((string) ($field['type'] ?? $field['field_type'] ?? 'text')) ?: 'text',
                'required' => (bool) ($field['required'] ?? $field['is_required'] ?? true),
                'options' => $field['options'] ?? $field['values'] ?? null,
            ];
        }

        return $out;
    }

    public static function defaultPlayerIdField(): array
    {
        return self::defaultIdField(BuyerIdentity::PLAYER);
    }

    public static function defaultIdField(string $kind): array
    {
        return [
            'name' => $kind === BuyerIdentity::USER ? 'username' : 'playerId',
            'label' => self::kindFieldLabel($kind),
            'type' => 'text',
            'required' => true,
            'options' => null,
        ];
    }

    /**
     * @param  list<array{name: string, label: string, type: string, required: bool, options: mixed}>  $fields
     * @return list<array{name: string, label: string, type: string, required: bool, options: mixed}>
     */
    public static function applyKind(array $fields, string $kind): array
    {
        $label = self::kindFieldLabel($kind);
        foreach ($fields as $index => $field) {
            $name = strtolower((string) ($field['name'] ?? ''));
            if (in_array($name, ['playerid', 'player_id', 'username', 'uid', 'user'], true)) {
                $fields[$index]['label'] = $label;
            }
        }

        return $fields;
    }

    public static function kindFieldLabel(string $kind): string
    {
        return BuyerIdentity::fieldLabel($kind);
    }

    private static function defaultLabel(string $name): string
    {
        return match (strtolower($name)) {
            'playerid', 'player_id' => 'شناسه بازیکن',
            'uid' => 'UID',
            'server' => 'سرور',
            'zoneid', 'zone_id' => 'منطقه',
            'playertag' => 'تگ بازیکن',
            'username' => 'نام کاربری',
            'riotid' => 'Riot ID',
            'epicid' => 'Epic ID',
            'email' => 'ایمیل',
            default => $name,
        };
    }
}
