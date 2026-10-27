<?php

namespace App\Modules\Catalog;

final class GameKeyPlatform
{
    public const GROUP_SLUG = 'game-keys';

    public const PER_PLATFORM = 300;

    public const PER_PLATFORM_MAX = 500;

    /** Upper bound on distinct Fazer platform strings in one game-key sync. */
    public const SLOT_CAP = 40;

    /**
     * Display order when several launchers are present. Any other Fazer value is still kept.
     *
     * @var list<string>
     */
    public const PREFERRED = [
        'steam',
        'playstation',
        'xbox',
        'nintendo',
        'ea',
        'epic',
        'ubisoft',
        'rockstar',
        'battlenet',
        'gog',
        'pc',
    ];

    /**
     * Shop browse chips for game keys: hardware family, not publisher.
     *
     * @var list<string>
     */
    public const STOREFRONTS = [
        'playstation',
        'xbox',
        'pc',
    ];

    /**
     * Hardware consoles that must not collapse into the PC bucket.
     *
     * @var list<string>
     */
    public const CONSOLE_STOREFRONTS = [
        'playstation',
        'xbox',
        'nintendo',
    ];

    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'steam' => 'Steam',
        'playstation' => 'PlayStation',
        'xbox' => 'Xbox',
        'nintendo' => 'Nintendo',
        'ea' => 'EA',
        'epic' => 'Epic',
        'ubisoft' => 'Ubisoft',
        'rockstar' => 'Rockstar',
        'battlenet' => 'Battle.net',
        'gog' => 'GOG',
        'pc' => 'PC',
    ];

    /**
     * Exact Fazer strings that collapse onto one shop bucket.
     *
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        'steam' => ['steam', 'steamworks', 'steam deck'],
        'playstation' => [
            'playstation',
            'playstation 3',
            'playstation 4',
            'playstation 5',
            'playstation network',
            'ps',
            'ps3',
            'ps4',
            'ps5',
            'psn',
            'sony',
        ],
        'xbox' => [
            'xbox',
            'xbox 360',
            'xbox one',
            'xbox series',
            'xbox series x',
            'xbox series s',
            'microsoft store',
            'ms store',
        ],
        'nintendo' => ['nintendo', 'nintendo switch', 'switch'],
        'ea' => ['ea', 'ea app', 'origin', 'ea desktop', 'electronic arts'],
        'epic' => ['epic', 'epic games', 'epic games store', 'egs'],
        'ubisoft' => ['ubisoft', 'ubisoft connect', 'uplay'],
        'rockstar' => ['rockstar', 'rockstar games', 'rockstar games launcher', 'social club', 'rgl'],
        'battlenet' => ['battlenet', 'battle.net', 'blizzard', 'battle net'],
        'gog' => ['gog', 'gog.com'],
        'pc' => ['pc', 'windows', 'win', 'computer'],
    ];

    public static function canonical(?string $raw): ?string
    {
        $needle = mb_strtolower(trim((string) $raw));
        if ($needle === '' || $needle === 'all') {
            return null;
        }
        foreach (self::ALIASES as $id => $aliases) {
            if (in_array($needle, $aliases, true)) {
                return $id;
            }
        }
        if (str_contains($needle, 'playstation') || preg_match('/\bps[345]?\b/u', $needle) === 1 || $needle === 'psn') {
            return 'playstation';
        }
        if (str_contains($needle, 'nintendo') || str_contains($needle, 'switch')) {
            return 'nintendo';
        }
        if (str_contains($needle, 'epic')) {
            return 'epic';
        }
        if (str_contains($needle, 'ubisoft') || str_contains($needle, 'uplay')) {
            return 'ubisoft';
        }
        if (str_contains($needle, 'battle.net') || str_contains($needle, 'battlenet') || str_contains($needle, 'blizzard')) {
            return 'battlenet';
        }
        if (str_contains($needle, 'rockstar') || str_contains($needle, 'social club')) {
            return 'rockstar';
        }
        if (str_contains($needle, 'xbox')) {
            return 'xbox';
        }
        if ($needle === 'ea' || str_starts_with($needle, 'ea ') || str_contains($needle, 'origin')) {
            return 'ea';
        }
        if (str_contains($needle, 'steam')) {
            return 'steam';
        }
        if (str_contains($needle, 'gog')) {
            return 'gog';
        }
        if ($needle === 'pc' || $needle === 'windows' || $needle === 'win') {
            return 'pc';
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', $needle) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : null;
    }

    /**
     * Fazer `platform` wins. `appid` is Steam-only when the platform field is empty.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromFazer(array $row): ?string
    {
        $raw = $row['platform'] ?? null;
        if (is_scalar($raw) && trim((string) $raw) !== '') {
            return self::canonical((string) $raw);
        }
        $appid = (int) ($row['appid'] ?? $row['app_id'] ?? 0);

        return $appid > 0 ? 'steam' : null;
    }

    public static function label(string $id): string
    {
        if (isset(self::LABELS[$id])) {
            return self::LABELS[$id];
        }

        return $id;
    }

    /**
     * PlayStation / Xbox / PC shop family. Steam, EA, Rockstar stay launchers under PC.
     */
    public static function storefront(?string $raw): ?string
    {
        $id = self::canonical($raw);
        if ($id === null) {
            return null;
        }
        if (in_array($id, self::CONSOLE_STOREFRONTS, true)) {
            return $id;
        }

        return 'pc';
    }

    public static function isStorefront(?string $raw): bool
    {
        $id = self::canonical($raw);

        return $id !== null && (in_array($id, self::STOREFRONTS, true) || $id === 'nintendo');
    }

    /**
     * @return list<string>
     */
    public static function matchNeedles(string $input): array
    {
        $id = self::canonical($input);
        if ($id === null) {
            return [];
        }
        $needles = self::ALIASES[$id] ?? [];
        $needles[] = $id;

        return array_values(array_unique($needles));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyFilter($query, string $platform, string $column = 'products.platform'): void
    {
        $id = self::canonical($platform);
        if ($id === 'pc') {
            $consoleNeedles = [];
            foreach (self::CONSOLE_STOREFRONTS as $console) {
                foreach (self::matchNeedles($console) as $needle) {
                    $consoleNeedles[] = $needle;
                }
            }
            $consoleNeedles = array_values(array_unique($consoleNeedles));
            if ($consoleNeedles === []) {
                return;
            }
            $placeholders = implode(',', array_fill(0, count($consoleNeedles), '?'));
            $query->whereNotNull($column)
                ->where($column, '!=', '')
                ->whereRaw('LOWER('.$column.') not in ('.$placeholders.')', $consoleNeedles);

            return;
        }
        $needles = self::matchNeedles($platform);
        if ($needles === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($needles), '?'));
        $query->whereRaw('LOWER('.$column.') in ('.$placeholders.')', $needles);
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    public static function mergeFacets(array $values): array
    {
        $ids = [];
        foreach ($values as $raw) {
            if (! is_scalar($raw)) {
                continue;
            }
            $id = self::canonical((string) $raw);
            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        return self::sortIds(array_values($ids));
    }

    /**
     * Collapse launchers (Steam / EA / Rockstar / …) into the PC shop chip.
     *
     * @param  list<mixed>  $values
     * @return list<string>
     */
    public static function mergeStorefrontFacets(array $values): array
    {
        $ids = [];
        foreach ($values as $raw) {
            if (! is_scalar($raw)) {
                continue;
            }
            $id = self::storefront((string) $raw);
            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        return self::sortStorefronts(array_values($ids));
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    public static function sortStorefronts(array $ids): array
    {
        $rank = array_flip(array_merge(self::STOREFRONTS, ['nintendo']));
        $ids = array_values(array_unique($ids));
        usort($ids, function (string $a, string $b) use ($rank): int {
            $ra = $rank[$a] ?? 1000;
            $rb = $rank[$b] ?? 1000;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcasecmp(self::label($a), self::label($b));
        });

        return $ids;
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    public static function sortIds(array $ids): array
    {
        $rank = array_flip(self::PREFERRED);
        $ids = array_values(array_unique($ids));
        usort($ids, function (string $a, string $b) use ($rank): int {
            $ra = $rank[$a] ?? 1000;
            $rb = $rank[$b] ?? 1000;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcasecmp(self::label($a), self::label($b));
        });

        return $ids;
    }
}
