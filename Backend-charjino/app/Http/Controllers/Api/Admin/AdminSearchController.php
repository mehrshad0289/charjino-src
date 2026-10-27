<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SmartSearch\SyncSearchSynonyms;
use App\Models\SearchSynonym;
use App\Models\SearchTag;
use App\Modules\SmartSearch\MeiliClientFactory;
use App\Modules\SmartSearch\PersianTextNormalizer;
use App\Modules\SmartSearch\SearchIndexWriteRegistry;
use App\Modules\SmartSearch\SmartSearchIndexManager;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSearchController extends Controller
{
    public function synonyms(): JsonResponse
    {
        return ApiResponse::ok(SearchSynonym::query()->orderByDesc('id')->get());
    }

    public function storeSynonym(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $row = SearchSynonym::query()->create($data);
        SyncSearchSynonyms::dispatch();

        return ApiResponse::ok($row, 'مترادف ذخیره شد.');
    }

    public function updateSynonym(Request $request, SearchSynonym $synonym): JsonResponse
    {
        $synonym->update($this->validated($request, $synonym->id));
        SyncSearchSynonyms::dispatch();

        return ApiResponse::ok($synonym->fresh(), 'مترادف به‌روز شد.');
    }

    public function destroySynonym(SearchSynonym $synonym): JsonResponse
    {
        $synonym->delete();
        SyncSearchSynonyms::dispatch();

        return ApiResponse::ok(['id' => $synonym->id], 'مترادف حذف شد.');
    }

    public function tags(): JsonResponse
    {
        return ApiResponse::ok(SearchTag::query()->with('catalogGroup')->orderBy('sort_order')->orderBy('id')->get());
    }

    public function updateTag(Request $request, SearchTag $tag): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['nullable', 'in:tag,brand'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'catalog_group_id' => ['nullable', 'integer', 'exists:catalog_groups,id'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['string', 'max:120'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);
        if (isset($data['aliases'])) {
            $data['aliases_text'] = implode(' ', $data['aliases']);
        }
        $tag->update($data);

        return ApiResponse::ok($tag->fresh(), 'برچسب به‌روز شد.');
    }

    public function health(): JsonResponse
    {
        $payload = [
            'driver' => config('smart-search.driver'),
            'prefix' => config('smart-search.index_prefix'),
            'indexes' => [],
        ];
        try {
            $client = MeiliClientFactory::makeForWrite();
            foreach (SearchIndexWriteRegistry::active() as $name => $uid) {
                try {
                    $stats = $client->index($uid)->stats();
                    $payload['indexes'][$name] = [
                        'uid' => $uid,
                        'documents' => (int) ($stats['numberOfDocuments'] ?? 0),
                        'isIndexing' => (bool) ($stats['isIndexing'] ?? false),
                    ];
                } catch (\Throwable) {
                    try {
                        $client->index($uid)->getSettings();
                        $payload['indexes'][$name] = [
                            'uid' => $uid,
                            'ready' => true,
                        ];
                    } catch (\Throwable) {
                        $payload['indexes'][$name] = ['uid' => $uid, 'missing' => true];
                    }
                }
            }
        } catch (\Throwable) {
            $payload['meili'] = 'unreachable';
        }

        return ApiResponse::ok($payload);
    }

    public function syncSynonyms(): JsonResponse
    {
        SmartSearchIndexManager::make()->configureAll();

        return ApiResponse::ok(['synonyms' => count(SmartSearchIndexManager::synonymMap())], 'مترادف‌ها همگام شد.');
    }

    /**
     * @return array{term: string, synonyms: list<string>, group_slug: ?string, is_active: bool}
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'term' => ['required', 'string', 'max:120'],
            'synonyms' => ['required', 'array', 'min:1'],
            'synonyms.*' => ['string', 'max:120'],
            'group_slug' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $term = PersianTextNormalizer::normalize((string) $data['term']);
        if ($term === '') {
            abort(422, 'عبارت مترادف خالی است.');
        }
        $synonyms = [];
        foreach ($data['synonyms'] as $syn) {
            $n = PersianTextNormalizer::normalize((string) $syn);
            if ($n !== '' && $n !== $term) {
                $synonyms[$n] = $n;
            }
        }
        if ($synonyms === []) {
            abort(422, 'حداقل یک مترادف معتبر لازم است.');
        }

        return [
            'term' => $term,
            'synonyms' => array_values($synonyms),
            'group_slug' => $data['group_slug'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
