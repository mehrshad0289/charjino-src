<?php

namespace App\Modules\Supplier\Shop2;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Shop2HostResolver
{
    public function ipv4(string $host): ?string
    {
        $cacheKey = 'shop2:dns:'.$host;
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $this->isIpv4($cached)) {
            return $cached;
        }

        $ip = $this->lookup($host);
        if ($ip) {
            Cache::put($cacheKey, $ip, 300);
        }

        return $ip;
    }

    /**
     * @return array<int, string>
     */
    public function curlResolve(string $host): array
    {
        if (app()->environment('testing')) {
            return [];
        }
        $ip = $this->ipv4($host);
        if (! $ip) {
            return [];
        }

        return [
            $host.':443:'.$ip,
            $host.':80:'.$ip,
        ];
    }

    private function lookup(string $host): ?string
    {
        $native = gethostbyname($host);
        if (is_string($native) && $native !== $host && $this->isIpv4($native)) {
            return $native;
        }

        foreach ([
            'https://1.1.1.1/dns-query',
            'https://8.8.8.8/resolve',
        ] as $endpoint) {
            $ip = $this->dohA($endpoint, $host);
            if ($ip) {
                return $ip;
            }
        }

        return $host === 'shop2topup.com' ? '104.26.8.143' : null;
    }

    private function dohA(string $endpoint, string $host): ?string
    {
        try {
            $response = Http::timeout(6)
                ->connectTimeout(4)
                ->withHeaders(['Accept' => 'application/dns-json'])
                ->get($endpoint, [
                    'name' => $host,
                    'type' => 'A',
                ]);
            $answers = $response->json('Answer');
            if (! is_array($answers)) {
                return null;
            }
            foreach ($answers as $answer) {
                $data = is_array($answer) ? ($answer['data'] ?? null) : null;
                if (is_string($data) && $this->isIpv4($data)) {
                    return $data;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function isIpv4(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }
}
