<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Modules\Identity\IdentityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly IdentityService $identity) {}

    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        return ApiResponse::ok($this->identity->requestOtp($request->string('phoneNumber')->toString()));
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        return ApiResponse::ok($this->identity->verifyOtp(
            $request->string('phoneNumber')->toString(),
            $request->string('code')->toString(),
        ));
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        return ApiResponse::ok($this->identity->refresh($request->string('refresh_token')->toString()));
    }

    public function adminLogin(AdminLoginRequest $request): JsonResponse
    {
        return ApiResponse::ok($this->identity->adminLogin(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        ));
    }

    public function forgotAdminPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:180'],
        ]);

        return ApiResponse::ok($this->identity->requestAdminPasswordReset((string) $data['email']));
    }

    public function resetAdminPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:180'],
            'token' => ['required', 'string', 'min:20', 'max:80'],
            'password' => ['required', 'string', 'min:8', 'max:80'],
        ]);

        return ApiResponse::ok($this->identity->resetAdminPassword(
            (string) $data['email'],
            (string) $data['token'],
            (string) $data['password'],
        ));
    }

    public function logout(Request $request): JsonResponse
    {
        $sub = $request->attributes->get('auth_user')['sub'] ?? null;
        if (! is_string($sub) || $sub === '') {
            return ApiResponse::fail('احراز هویت نامعتبر است', 401);
        }

        return ApiResponse::ok($this->identity->logout($sub));
    }

    public function me(Request $request): JsonResponse
    {
        $sub = $request->attributes->get('auth_user')['sub'] ?? null;
        if (! is_string($sub) || $sub === '') {
            return ApiResponse::fail('احراز هویت نامعتبر است', 401);
        }

        return ApiResponse::ok($this->identity->me($sub));
    }
}
