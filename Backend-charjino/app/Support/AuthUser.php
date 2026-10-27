<?php

namespace App\Support;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Http\Request;

final class AuthUser
{
    public static function idOrNull(Request $request): ?string
    {
        $sub = $request->attributes->get('auth_user')['sub'] ?? null;

        return is_string($sub) && $sub !== '' ? $sub : null;
    }

    public static function id(Request $request): string
    {
        $sub = self::idOrNull($request);
        if ($sub === null) {
            throw new ApiException('احراز هویت نامعتبر است', 401);
        }

        return $sub;
    }

    public static function user(Request $request): User
    {
        $user = User::query()->find(self::id($request));
        if (! $user) {
            throw new ApiException('احراز هویت نامعتبر است', 401);
        }

        return $user;
    }
}
