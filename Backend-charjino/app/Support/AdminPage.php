<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminPage
{
    /**
     * @return array{page: int, perPage: int, total: int, totalPages: int}
     */
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
        ];
    }

    /**
     * @return array{page: int, perPage: int}
     */
    public static function query(\Illuminate\Http\Request $request, int $defaultPerPage = 10): array
    {
        $perPage = (int) $request->query('perPage', $defaultPerPage);

        return [
            'page' => max(1, (int) $request->query('page', 1)),
            'perPage' => min(100, max(1, $perPage)),
        ];
    }
}
