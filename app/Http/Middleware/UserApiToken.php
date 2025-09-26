<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
//use Illuminate\Support\Facades\DB;

class UserApiToken
{
    public function handle(Request $request, Closure $next)
    {
        // Accept either Bearer token or X-Api-Key header
        $token = $request->bearerToken() ?: $request->header('X-Api-Key');

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 1) Allow static API key from .env/config
        $staticKey = config('te.api_key');
        if (!empty($staticKey) && hash_equals($staticKey, $token)) {
            return $next($request);
        }

        return $next($request);
    }
}