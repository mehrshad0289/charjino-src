<?php

namespace App\Modules\SmartSearch;

use App\Modules\SmartSearch\Contracts\SmartSearchEngine;
use App\Modules\SmartSearch\Data\SmartSearchCandidates;
use App\Modules\SmartSearch\Data\SmartSearchRequest;
use App\Support\SafeLog;
use Meilisearch\Exceptions\ApiException as MeiliApiException;

final class FailoverSmartSearchEngine implements SmartSearchEngine
{
    public function __construct(
        private readonly MeilisearchSmartSearchEngine $meili,
        private readonly DatabaseSmartSearchEngine $database,
    ) {}

    public function search(SmartSearchRequest $request): SmartSearchCandidates
    {
        try {
            return $this->meili->search($request);
        } catch (\Throwable $e) {
            if ($this->isClientError($e)) {
                throw $e;
            }
            SafeLog::warning('smart_search.failover', [
                'q' => mb_substr($request->normalized, 0, 100),
                'code' => 'MEILI_UNAVAILABLE',
            ]);

            return $this->database->search($request);
        }
    }

    private function isClientError(\Throwable $e): bool
    {
        if ($e instanceof MeiliApiException) {
            $code = (int) ($e->httpStatus ?? $e->getCode());

            return $code >= 400 && $code < 500 && $code !== 408 && $code !== 429;
        }

        return false;
    }
}
