<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function ok(mixed $data = null, string $message = 'موفق', int $status = 200): JsonResponse
    {
        if (is_array($data) && isset($data['message']) && is_string($data['message']) && $data['message'] !== '') {
            $message = Utf8::string($data['message']);
        }

        return response()->json([
            'success' => true,
            'statusCode' => $status,
            'data' => Utf8::deep($data),
            'message' => Utf8::string($message),
            'timestamp' => now()->toIso8601String(),
        ], $status, [], Utf8::JSON_FLAGS);
    }

    public static function fail(string $message, int $status = 400, ?string $code = null, mixed $data = null): JsonResponse
    {
        $body = [
            'success' => false,
            'statusCode' => $status,
            'message' => Utf8::string($message),
            'timestamp' => now()->toIso8601String(),
        ];
        if (is_string($code) && $code !== '') {
            $body['error'] = ['code' => $code];
        }
        if ($data !== null) {
            $body['data'] = Utf8::deep($data);
        }

        return response()->json($body, $status, [], Utf8::JSON_FLAGS);
    }
}
