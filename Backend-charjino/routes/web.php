<?php

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

if (! function_exists('charjino_admin_signin_url')) {
    function charjino_admin_signin_url(Request $request): string
    {
        $host = $request->getHost();
        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            return $request->getScheme().'://'.$host.':3002/signin';
        }

        return rtrim((string) config('charjino.admin.front_url'), '/').'/signin';
    }
}

Route::get('/', function () {
    return ApiResponse::ok([
        'app' => 'charjino-api',
        'stack' => 'laravel',
        'health' => '/api/health',
    ], 'چارژینو API');
});

Route::get('/signin', function (Request $request) {
    return redirect()->away(charjino_admin_signin_url($request));
});

Route::get('/login', function (Request $request) {
    return redirect()->away(charjino_admin_signin_url($request));
});

Route::get('/storage/{path}', function (string $path) {
    $full = storage_path('app/public/'.$path);
    $realStorage = realpath(storage_path('app/public'));
    $realFile = realpath($full);
    $storage = $realStorage ? str_replace('\\', '/', $realStorage) : '';
    $file = $realFile ? str_replace('\\', '/', $realFile) : '';
    abort_unless($storage !== '' && $file !== '' && str_starts_with($file, $storage.'/') && is_file($realFile), 404);

    return response()->file($realFile);
})->where('path', '.*');
