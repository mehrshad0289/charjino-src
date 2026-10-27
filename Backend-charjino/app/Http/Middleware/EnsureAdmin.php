<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->attributes->get('auth_user');
        if (! is_array($payload) || ($payload['role'] ?? null) !== 'admin') {
            throw new ApiException('دسترسی مجاز نیست.', 403);
        }

        return $next($request);
    }
}
