<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\OptionalJwt;
use App\Http\Middleware\VerifyTorobToken;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'jwt' => AuthenticateJwt::class,
            'jwt.optional' => OptionalJwt::class,
            'admin' => EnsureAdmin::class,
            'torob.jwt' => VerifyTorobToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ApiException $e) {
            $response = ApiResponse::fail($e->getMessage(), $e->status, $e->errorCode, $e->data);
            if ($e->status === 301 && is_array($e->data) && isset($e->data['to'])) {
                $response->headers->set('Location', (string) $e->data['to']);
            }

            return $response;
        });

        $exceptions->render(function (ValidationException $e) {
            $message = collect($e->errors())->flatten()->filter()->first() ?? 'ورودی نامعتبر است.';

            return ApiResponse::fail((string) $message, 422);
        });

        $exceptions->render(function (AuthenticationException $e) {
            return ApiResponse::fail('احراز هویت نامعتبر است', 401);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e) {
            $response = ApiResponse::fail('تعداد درخواست بیش از حد مجاز است.', 429);
            $retry = $e->getHeaders()['Retry-After'] ?? $e->getHeaders()['retry-after'] ?? null;
            if ($retry !== null) {
                $response->headers->set('Retry-After', (string) $retry);
            }

            return $response;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('admin/v1/*')) {
                $host = $request->getHost();
                $adminSignIn = in_array($host, ['localhost', '127.0.0.1'], true)
                    ? $request->getScheme().'://'.$host.':3002/signin'
                    : rtrim((string) config('charjino.admin.front_url'), '/').'/signin';

                if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
                    return redirect()->away($adminSignIn);
                }

                return ApiResponse::fail(
                    'این مسیر مربوط به بوم‌وبنا است. پنل شارژینو را از '.$adminSignIn.' باز کنید و با ایمیل وارد شوید.',
                    404
                );
            }

            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::fail('مسیر پیدا نشد.', 404);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::fail($e->getMessage() !== '' ? $e->getMessage() : 'درخواست انجام نشد', $e->getStatusCode());
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = config('app.debug') ? $e->getMessage() : 'درخواست انجام نشد';

            return ApiResponse::fail($message !== '' ? $message : 'درخواست انجام نشد', 500);
        });
    })->create();
