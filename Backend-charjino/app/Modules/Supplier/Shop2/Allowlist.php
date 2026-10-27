<?php

namespace App\Modules\Supplier\Shop2;

final class Allowlist
{
    /** @var list<string> */
    private const GET_EXACT = [
        'account',
        'catalog/big-categories',
        'catalog/categories',
        'catalog/subcategories',
        'orders',
    ];

    /** @var list<string> */
    private const POST_EXACT = [
        'player/validate',
        'orders/batch',
        'orders/create',
    ];

    /**
     * @param  list<string>  $parts
     */
    public static function allows(string $method, array $parts): bool
    {
        $path = implode('/', $parts);
        if ($method === 'GET') {
            if (in_array($path, self::GET_EXACT, true)) {
                return true;
            }
            if (preg_match('#^catalog/subcategory/[^/]+/price$#', $path)) {
                return true;
            }
            if (preg_match('#^catalog/category/[^/]+/requirements$#', $path)) {
                return true;
            }

            return (bool) preg_match('#^orders/[^/]+$#', $path)
                && ($parts[1] ?? '') !== 'create'
                && ($parts[1] ?? '') !== 'batch';
        }

        return $method === 'POST' && in_array($path, self::POST_EXACT, true);
    }
}
