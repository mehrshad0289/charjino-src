<?php

namespace App\Modules\SmartSearch;

use GuzzleHttp\Client as GuzzleClient;
use Meilisearch\Client;

final class MeiliClientFactory
{
    public static function make(?int $timeoutMs = null): Client
    {
        $host = (string) config('smart-search.meilisearch.host', config('scout.meilisearch.host'));
        $key = (string) config('smart-search.meilisearch.key', config('scout.meilisearch.key'));
        $timeoutMs = $timeoutMs ?? (int) config('smart-search.timeout_ms', 1200);
        $seconds = max(0.4, $timeoutMs / 1000);
        $http = new GuzzleClient([
            'timeout' => $seconds,
            'connect_timeout' => min(1.5, $seconds),
            'http_errors' => false,
        ]);

        return new Client($host, $key !== '' ? $key : null, $http);
    }

    public static function makeForWrite(): Client
    {
        return self::make(30000);
    }

    public static function wait(Client $client, int|string $taskUid, int $timeoutMs = 120000): void
    {
        $task = $client->waitForTask($taskUid, $timeoutMs, 50);
        $status = is_array($task) ? ($task['status'] ?? '') : '';
        if ($status !== 'succeeded') {
            $error = is_array($task) ? json_encode($task['error'] ?? $task) : 'task failed';
            throw new \RuntimeException('Meilisearch task '.$taskUid.' failed: '.$error);
        }
    }
}
