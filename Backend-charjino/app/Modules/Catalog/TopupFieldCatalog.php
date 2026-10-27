<?php

namespace App\Modules\Catalog;

final class TopupFieldCatalog
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public static function enrich(array $row): ?array
    {
        $key = CatalogExclusion::fieldKey($row);
        if ($key === '' || CatalogExclusion::isCredentialKey($key)) {
            return null;
        }
        $meta = self::meta($key, $row);
        $type = strtolower(trim((string) ($row['type'] ?? $meta['type'])));
        $options = self::options($row['options'] ?? $row['values'] ?? null);
        if ($options !== []) {
            $type = 'select';
        } elseif ($type !== 'select') {
            $type = 'text';
        }
        $required = array_key_exists('required', $row)
            ? filter_var($row['required'], FILTER_VALIDATE_BOOLEAN)
            : (array_key_exists('is_required', $row)
                ? filter_var($row['is_required'], FILTER_VALIDATE_BOOLEAN)
                : true);

        return [
            'key' => $key,
            'name' => $key,
            'label' => $meta['label'],
            'labelFa' => $meta['labelFa'],
            'help' => $meta['help'],
            'type' => $type,
            'required' => $required,
            'pattern' => $meta['pattern'],
            'options' => $options === [] ? null : $options,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{label: string, labelFa: string, help: string, type: string, pattern: string}
     */
    public static function meta(string $key, array $row = []): array
    {
        $fazerLabel = trim((string) ($row['label'] ?? $row['title'] ?? $row['name'] ?? ''));
        $numeric = (bool) preg_match('/number|numeric|digit/i', (string) ($row['type'] ?? '').' '.$fazerLabel);

        return match (strtolower($key)) {
            'player_id' => [
                'label' => $fazerLabel !== '' ? $fazerLabel : 'Player ID',
                'labelFa' => 'شناسهٔ بازیکن (Player ID)',
                'help' => 'از پروفایل داخل بازی کپی کنید',
                'type' => 'text',
                'pattern' => $numeric ? '^\d{4,20}$' : '^[A-Za-z0-9._-]{3,32}$',
            ],
            'user_id' => [
                'label' => $fazerLabel !== '' ? $fazerLabel : 'User ID',
                'labelFa' => 'شناسهٔ کاربری (User ID)',
                'help' => 'از پروفایل داخل بازی یا برنامه',
                'type' => 'text',
                'pattern' => '^[A-Za-z0-9._-]{3,40}$',
            ],
            'uid' => [
                'label' => $fazerLabel !== '' ? $fazerLabel : 'UID',
                'labelFa' => 'UID',
                'help' => 'از پروفایل داخل بازی یا برنامه',
                'type' => 'text',
                'pattern' => '^[A-Za-z0-9._-]{3,32}$',
            ],
            'server_id' => [
                'label' => $fazerLabel !== '' ? $fazerLabel : 'Server ID',
                'labelFa' => 'شناسهٔ سرور (Server ID)',
                'help' => 'عدد داخل پرانتز کنار Player ID در MLBB',
                'type' => 'text',
                'pattern' => '^\d{1,8}$',
            ],
            'server' => [
                'label' => $fazerLabel !== '' ? $fazerLabel : 'Server',
                'labelFa' => 'سرور',
                'help' => 'اگر فهرست دارد یکی را انتخاب کنید',
                'type' => 'text',
                'pattern' => '^.{1,64}$',
            ],
            'riot_id' => [
                'label' => $fazerLabel !== '' ? $fazerLabel : 'Riot ID',
                'labelFa' => 'Riot ID',
                'help' => 'به شکل Name#TAG؛ حساب Riot باید در ریجن همین محصول باشد',
                'type' => 'text',
                'pattern' => '^.{3,16}#[A-Za-z0-9]{2,5}$',
            ],
            'bigo_id' => [
                'label' => $fazerLabel !== '' ? $fazerLabel : 'Bigo ID',
                'labelFa' => 'شناسهٔ Bigo',
                'help' => 'از پروفایل Bigo Live',
                'type' => 'text',
                'pattern' => '^[A-Za-z0-9._-]{3,32}$',
            ],
            'role_id', 'character_id' => [
                'label' => $fazerLabel !== '' ? $fazerLabel : $key,
                'labelFa' => 'شناسهٔ نقش یا کاراکتر',
                'help' => 'از پروفایل کاراکتر',
                'type' => 'text',
                'pattern' => '^[A-Za-z0-9._-]{2,40}$',
            ],
            'character_name' => [
                'label' => $fazerLabel !== '' ? $fazerLabel : 'Character name',
                'labelFa' => 'نام کاراکتر',
                'help' => 'دقیقاً همان‌طور که در بازی نوشته شده',
                'type' => 'text',
                'pattern' => '^.{1,32}$',
            ],
            'account_id', 'open_id', 'sid', 'imo_id', 'support_id', 'tiktok_user_id' => [
                'label' => $fazerLabel !== '' ? $fazerLabel : $key,
                'labelFa' => $fazerLabel !== '' ? $fazerLabel.' — شناسهٔ حساب' : 'شناسهٔ حساب در همین سرویس',
                'help' => 'از پروفایل همان سرویس',
                'type' => 'text',
                'pattern' => '^[A-Za-z0-9._@-]{2,64}$',
            ],
            'region', 'platform' => [
                'label' => $fazerLabel !== '' ? $fazerLabel : $key,
                'labelFa' => $key === 'region' ? 'ریجن' : 'پلتفرم',
                'help' => 'اگر فهرست دارد یکی را انتخاب کنید',
                'type' => 'text',
                'pattern' => '^.{1,64}$',
            ],
            'steamlogin' => [
                'label' => 'Steam login',
                'labelFa' => 'نام کاربری ورود استیم (Steam login)',
                'help' => 'همان نامی که با آن وارد Steam می‌شوید، نه نام نمایشی',
                'type' => 'text',
                'pattern' => '^[A-Za-z0-9._-]{2,64}$',
            ],
            'invite_url' => [
                'label' => 'Steam invite link',
                'labelFa' => 'لینک دعوت دوستی استیم',
                'help' => 'Steam ← Friends ← Add a Friend ← Create Invite Link',
                'type' => 'text',
                'pattern' => '^(https://)?s\.team/p/[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)?$',
            ],
            'telegram_username' => [
                'label' => 'Telegram username',
                'labelFa' => 'نام کاربری تلگرام',
                'help' => 'با یا بدون @',
                'type' => 'text',
                'pattern' => '^@?[A-Za-z][A-Za-z0-9_]{4,31}$',
            ],
            default => [
                'label' => $fazerLabel !== '' ? $fazerLabel : $key,
                'labelFa' => $fazerLabel !== '' ? $fazerLabel : $key,
                'help' => 'طبق راهنمای داخل بازی یا برنامه پر کنید',
                'type' => 'text',
                'pattern' => '^.{1,64}$',
            ],
        };
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public static function options(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (is_string($row) || is_int($row) || is_float($row)) {
                $value = trim((string) $row);
                if ($value !== '') {
                    $out[] = ['label' => $value, 'value' => $value];
                }

                continue;
            }
            if (! is_array($row)) {
                continue;
            }
            $value = trim((string) ($row['value'] ?? $row['id'] ?? $row['key'] ?? $row['name'] ?? ''));
            $label = trim((string) ($row['label'] ?? $row['title'] ?? $row['name'] ?? $value));
            if ($value === '') {
                continue;
            }
            $out[] = ['label' => $label !== '' ? $label : $value, 'value' => $value];
        }

        return $out;
    }
}
