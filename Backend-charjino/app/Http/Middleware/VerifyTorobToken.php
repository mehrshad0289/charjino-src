<?php

namespace App\Http\Middleware;

use App\Services\Torob\TorobJwtValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class VerifyTorobToken
{
    public function __construct(private TorobJwtValidator $validator) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->validator->validate(
                $request->header('X-Torob-Token'),
                $request->header('X-Torob-Token-Version')
            );
        } catch (UnexpectedValueException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        } catch (\Throwable) {
            return response()->json(['error' => 'invalid token'], 401);
        }

        return $next($request);
    }
}
