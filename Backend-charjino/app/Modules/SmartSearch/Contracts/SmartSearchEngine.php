<?php

namespace App\Modules\SmartSearch\Contracts;

use App\Modules\SmartSearch\Data\SmartSearchCandidates;
use App\Modules\SmartSearch\Data\SmartSearchRequest;

interface SmartSearchEngine
{
    public function search(SmartSearchRequest $request): SmartSearchCandidates;
}
