<?php

namespace App\Http\Middleware;

use App\Support\Jwt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OptionalJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            try {
                $payload = Jwt::decode(substr($header, 7), (string) config('charjino.jwt.access_secret'));
                $request->attributes->set('auth_user', $payload);
            } catch (Throwable) {
            }
        }

        return $next($request);
    }
}
