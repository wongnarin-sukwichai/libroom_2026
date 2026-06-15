<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = config('services.libroom.v2_api_token');

        if (!$token || $request->bearerToken() !== $token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
