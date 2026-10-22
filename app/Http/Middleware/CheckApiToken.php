<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckApiToken
{
    /**
     * @param  string  $key  ชื่อ token ใน config('services.libroom.*') — เช่น `api.token:stats_api_token`
     *                       แยก token ต่อผู้ใช้ API จะได้เพิกถอนทีละรายได้
     */
    public function handle(Request $request, Closure $next, string $key = 'v2_api_token')
    {
        $token = config("services.libroom.{$key}");

        if (!$token || !is_string($request->bearerToken()) || !hash_equals($token, $request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
