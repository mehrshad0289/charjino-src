<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Modules\Supplier\Shop2\Shop2Adapter;
use App\Modules\Supplier\Shop2\Shop2Client;
use App\Modules\Supplier\Shop2\SkuDiscovery;
use App\Modules\Supplier\Shop2\SkuMap;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSupplierController extends Controller
{
    public function show(Shop2Client $shop2, Shop2Adapter $adapter): JsonResponse
    {
        $status = $shop2->status();
        $status['skuCount'] = count(SkuMap::skus());
        $status['account'] = null;
        $status['accountError'] = null;
        $status['accountErrorCode'] = null;
        if ($shop2->configured()) {
            try {
                $account = $adapter->account();
                $status['account'] = [
                    'email' => $account['email'],
                    'wallet' => $account['wallet'],
                    'currency' => $account['currency'] ?? 'USD',
                    'active' => (bool) ($account['active'] ?? true),
                ];
            } catch (ApiException $e) {
                $status['accountError'] = \App\Support\CustomerError::message($e->errorCode, $e->getMessage());
                $status['accountErrorCode'] = $e->errorCode;
            }
        }

        return ApiResponse::ok($status);
    }

    public function discover(SkuDiscovery $discovery): JsonResponse
    {
        $map = $discovery->discover();

        return ApiResponse::ok([
            'updatedAt' => $map['updated_at'] ?? null,
            'skuCount' => count($map['skus'] ?? []),
            'skus' => $map['skus'] ?? [],
        ], 'کشف کاتالوگ PUBG و COD ذخیره شد.');
    }

    public function skuMap(): JsonResponse
    {
        $map = SkuMap::read();

        return ApiResponse::ok([
            'provider' => $map['provider'] ?? 'shop2topup',
            'currency' => $map['currency'] ?? 'USD',
            'updatedAt' => $map['updated_at'] ?? null,
            'skus' => $map['skus'] ?? [],
        ]);
    }

    public function price(Request $request, Shop2Adapter $adapter): JsonResponse
    {
        $itemId = $this->resolveItemId($request);
        $price = $adapter->livePrice($itemId);

        return ApiResponse::ok([
            'itemId' => $itemId,
            'unitPrice' => $price['unitPrice'],
            'currency' => $price['currency'],
        ]);
    }

    public function validatePlayer(Request $request, Shop2Adapter $adapter): JsonResponse
    {
        $data = $request->validate([
            'itemId' => ['nullable', 'integer', 'min:1'],
            'internalCode' => ['nullable', 'string', 'max:80'],
            'playerId' => ['required', 'string', 'max:64'],
            'server' => ['nullable', 'string', 'max:64'],
            'zoneId' => ['nullable', 'string', 'max:64'],
        ]);

        $itemId = $this->resolveItemId($request);
        $result = $adapter->validatePlayer(
            $itemId,
            (string) $data['playerId'],
            isset($data['server']) ? (string) $data['server'] : null,
            isset($data['zoneId']) ? (string) $data['zoneId'] : null,
        );

        return ApiResponse::ok($result + ['itemId' => $itemId]);
    }

    private function resolveItemId(Request $request): int
    {
        $itemId = (int) $request->input('itemId', $request->query('itemId', 0));
        if ($itemId > 0) {
            return $itemId;
        }

        $code = trim((string) $request->input('internalCode', $request->query('internalCode', '')));
        if ($code !== '') {
            $row = SkuMap::find($code);
            $mapped = (int) ($row['item_id'] ?? $row['sub_category_id'] ?? 0);
            if ($mapped > 0) {
                return $mapped;
            }
        }

        throw new ApiException('شناسهٔ بسته تأمین‌کننده لازم است.', 422, 'MISSING_REQUIRED_FIELD');
    }
}
