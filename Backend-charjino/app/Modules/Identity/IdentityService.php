<?php

namespace App\Modules\Identity;

use App\Exceptions\ApiException;
use App\Models\OtpChallenge;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\Jwt;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class IdentityService
{
    /**
     * @return array{message: string}
     */
    public function requestOtp(string $phoneNumber): array
    {
        if (! $this->usesFixedDevOtp()) {
            $this->enforceOtpRate($phoneNumber);
        }
        $code = $this->nextOtpCode();

        OtpChallenge::query()->updateOrCreate(
            ['phone' => $phoneNumber],
            [
                'code_hash' => hash('sha256', $code),
                'attempts' => 0,
                'expires_at' => now()->addSeconds((int) config('charjino.otp.ttl_seconds')),
            ]
        );

        if (config('app.env') !== 'production') {
            Log::info('[otp] '.Phone::mask($phoneNumber).' '.$code);
        }

        if ($this->usesFixedDevOtp()) {
            return ['message' => 'کد تأیید ارسال شد.'];
        }

        $sms = app(SmsPatternClient::class);
        if ($sms->enabled()) {
            $sent = $sms->sendOtp($phoneNumber, $code);
            if (! $sent['success']) {
                OtpChallenge::query()->where('phone', $phoneNumber)->delete();
                throw new ApiException($sent['error'] ?? 'ارسال پیامک انجام نشد.', 503);
            }
        } elseif (config('app.env') === 'production') {
            OtpChallenge::query()->where('phone', $phoneNumber)->delete();
            throw new ApiException('ارسال پیامک پیکربندی نشده است.', 503);
        }

        return ['message' => 'کد تأیید ارسال شد.'];
    }

    /**
     * @return array{access_token: string, refresh_token: string, user: array<string, mixed>}
     */
    public function verifyOtp(string $phoneNumber, string $code): array
    {
        $otp = OtpChallenge::query()->where('phone', $phoneNumber)->first();
        if (! $otp) {
            throw new ApiException('کد تأیید نامعتبر است.', 401);
        }
        if ($otp->expires_at->isPast()) {
            $otp->delete();
            throw new ApiException('کد تأیید منقضی شده است.', 401);
        }
        if ($otp->attempts >= (int) config('charjino.otp.max_attempts')) {
            $otp->delete();
            throw new ApiException('تعداد تلاش بیش از حد مجاز است.', 429);
        }

        $otp->increment('attempts');
        if (! hash_equals($otp->code_hash, hash('sha256', $code))) {
            throw new ApiException('کد تأیید نامعتبر است.', 401);
        }

        $otp->delete();
        $user = $this->upsertShopUser($phoneNumber);

        return $this->issueSession($user);
    }

    /**
     * @return array{access_token: string, refresh_token: string, user: array<string, mixed>}
     */
    public function adminLogin(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->where('role', 'admin')->first();
        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw new ApiException('ایمیل یا رمز عبور نادرست است.', 401);
        }

        return $this->issueSession($user);
    }

    /**
     * @return array{message: string}
     */
    public function requestAdminPasswordReset(string $email): array
    {
        $normalized = mb_strtolower(trim($email));
        $message = 'اگر این ایمیل در سیستم باشد، لینک بازیابی ارسال می‌شود.';
        $user = User::query()->where('email', $normalized)->where('role', 'admin')->first();
        if (! $user) {
            return ['message' => $message];
        }
        $plain = Str::lower(Str::random(40));
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $normalized],
            ['token' => Hash::make($plain), 'created_at' => now()],
        );
        $link = rtrim((string) config('services.admin.url'), '/').'/reset-password?token='.urlencode($plain).'&email='.urlencode($normalized);
        $body = "برای تعیین رمز جدید پنل ادمین شارژینو این پیوند را باز کنید:\n{$link}\n\nاین پیوند یک ساعت اعتبار دارد.";
        try {
            Mail::raw($body, function ($message) use ($user): void {
                $message->to((string) $user->email)->subject('بازیابی رمز پنل شارژینو');
            });
        } catch (\Throwable $e) {
            Log::warning('admin password reset mail failed', ['message' => $e->getMessage()]);
        }
        if (app()->environment('testing')) {
            Cache::put('admin-reset:'.$normalized, $plain, 3600);
        } elseif (config('app.env') !== 'production') {
            Log::info('[admin-reset] '.$normalized);
        }

        return ['message' => $message];
    }

    /**
     * @return array{message: string}
     */
    public function resetAdminPassword(string $email, string $token, string $password): array
    {
        $normalized = mb_strtolower(trim($email));
        $row = DB::table('password_reset_tokens')->where('email', $normalized)->first();
        if (! $row || ! Hash::check($token, (string) $row->token)) {
            throw new ApiException('پیوند بازیابی نامعتبر است.', 422, 'RESET_INVALID');
        }
        $created = $row->created_at ? Carbon::parse($row->created_at) : null;
        if (! $created || $created->lt(now()->subHour())) {
            DB::table('password_reset_tokens')->where('email', $normalized)->delete();
            throw new ApiException('پیوند بازیابی منقضی شده است.', 422, 'RESET_EXPIRED');
        }
        $user = User::query()->where('email', $normalized)->where('role', 'admin')->first();
        if (! $user) {
            throw new ApiException('پیوند بازیابی نامعتبر است.', 422, 'RESET_INVALID');
        }
        $user->password = $password;
        $user->save();
        DB::table('password_reset_tokens')->where('email', $normalized)->delete();
        RefreshToken::query()->where('user_id', $user->id)->update(['revoked_at' => now()]);

        return ['message' => 'رمز عبور به‌روز شد.'];
    }

    /**
     * @return array{access_token: string}
     */
    public function refresh(string $refreshToken): array
    {
        $record = RefreshToken::query()
            ->where('token_hash', hash('sha256', $refreshToken))
            ->whereNull('revoked_at')
            ->first();

        if (! $record || $record->expires_at->isPast()) {
            throw new ApiException('توکن بازیابی موجود نیست', 401);
        }

        $user = User::query()->find($record->user_id);
        if (! $user) {
            throw new ApiException('توکن بازیابی موجود نیست', 401);
        }

        return ['access_token' => $this->signAccess($user)];
    }

    /**
     * @return array{message: string}
     */
    public function logout(string $userId): array
    {
        RefreshToken::query()->where('user_id', $userId)->update(['revoked_at' => now()]);

        return ['message' => 'خروج انجام شد.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function me(string $userId): array
    {
        $user = User::query()->find($userId);
        if (! $user) {
            throw new ApiException('احراز هویت نامعتبر است', 401);
        }

        return $user->toAuthUser();
    }

    /**
     * Shop display name only — no national ID or address.
     *
     * @return array<string, mixed>
     */
    public function updateProfile(string $userId, string $name): array
    {
        $user = User::query()->find($userId);
        if (! $user) {
            throw new ApiException('احراز هویت نامعتبر است', 401);
        }
        if ($user->isAdmin()) {
            throw new ApiException('این مسیر مخصوص حساب فروشگاه است.', 403);
        }

        $trimmed = trim($name);
        if (mb_strlen($trimmed) < 2) {
            throw new ApiException('نام نمایشی کوتاه است.', 422, 'PROFILE_NAME_SHORT');
        }

        $user->name = mb_substr($trimmed, 0, 40);
        $user->save();

        return $user->toAuthUser();
    }

    /**
     * @return array{access_token: string, refresh_token: string, user: array<string, mixed>}
     */
    private function issueSession(User $user): array
    {
        $refreshToken = bin2hex(random_bytes(48));
        RefreshToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $refreshToken),
            'expires_at' => now()->addSeconds((int) config('charjino.jwt.refresh_ttl')),
        ]);

        return [
            'access_token' => $this->signAccess($user),
            'refresh_token' => $refreshToken,
            'user' => $user->toAuthUser(),
        ];
    }

    private function signAccess(User $user): string
    {
        $payload = [
            'sub' => $user->id,
            'role' => $user->role,
            'phoneNumber' => $user->phone,
            'email' => $user->email,
        ];

        return Jwt::encode(
            $payload,
            (string) config('charjino.jwt.access_secret'),
            (int) config('charjino.jwt.access_ttl'),
        );
    }

    private function upsertShopUser(string $phoneNumber): User
    {
        $existing = User::query()->where('phone', $phoneNumber)->first();
        if ($existing) {
            return $existing;
        }

        return User::query()->create([
            'phone' => $phoneNumber,
            'role' => 'user',
            'status' => 'active',
        ]);
    }

    private function usesFixedDevOtp(): bool
    {
        $env = (string) config('app.env');

        return in_array($env, ['local', 'development'], true)
            && trim((string) config('charjino.otp.dev_code')) !== '';
    }

    private function nextOtpCode(): string
    {
        $configured = trim((string) config('charjino.otp.dev_code'));
        if (config('app.env') !== 'production' && $configured !== '') {
            if (! preg_match('/^\d{1,8}$/', $configured)) {
                throw new ApiException('DEV_OTP_CODE نامعتبر است.', 400);
            }

            return str_pad($configured, 6, '0', STR_PAD_LEFT);
        }

        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function enforceOtpRate(string $phoneNumber): void
    {
        $window = (int) config('charjino.otp.rate_window_seconds');
        $max = (int) config('charjino.otp.rate_max');
        $key = 'otp-rate:'.$phoneNumber;
        $hits = array_values(array_filter(
            Cache::get($key, []),
            fn ($stamp) => is_int($stamp) && (time() - $stamp) < $window
        ));

        if (count($hits) >= $max) {
            throw new ApiException('تعداد درخواست بیش از حد مجاز است.', 429);
        }

        $hits[] = time();
        Cache::put($key, $hits, $window);
    }
}
