<?php

namespace App\Modules\SmartSearch;

use App\Modules\Catalog\SmartSearchService as DatabaseCatalogSearch;
use App\Modules\SmartSearch\Contracts\SmartSearchEngine;
use App\Modules\SmartSearch\Data\SmartSearchCandidates;
use App\Modules\SmartSearch\Data\SmartSearchRequest;

final class DatabaseSmartSearchEngine implements SmartSearchEngine
{
    public function __construct(private readonly DatabaseCatalogSearch $inner)
    {
    }

    public function search(SmartSearchRequest $request): SmartSearchCandidates
    {
        $payload = $this->inner->search($request->raw, $request->page, $request->perPage, $request->mode);

        return new SmartSearchCandidates(
            (string) ($payload['mode'] ?? 'empty'),
            (string) ($payload['query'] ?? $request->normalized),
            is_array($payload['items'] ?? null) ? $payload['items'] : [],
            is_array($payload['pagination'] ?? null) ? $payload['pagination'] : [
                'current_page' => $request->page,
                'last_page' => 1,
                'has_more_pages' => false,
            ],
        );
    }
}
