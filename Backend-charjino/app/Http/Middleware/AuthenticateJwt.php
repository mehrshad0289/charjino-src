<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Support\Jwt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            throw new ApiException('احراز هویت نامعتبر است', 401);
        }

        $payload = Jwt::decode(substr($header, 7), (string) config('charjino.jwt.access_secret'));
        $request->attributes->set('auth_user', $payload);

        return $next($request);
    }
}
