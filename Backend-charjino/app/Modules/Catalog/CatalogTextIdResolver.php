<?php

namespace App\Modules\Catalog;

interface CatalogTextIdResolver
{
    /**
     * @return list<string>|null
     */
    public function handles(string $normalized, ?string $groupSlug = null): ?array;
}
