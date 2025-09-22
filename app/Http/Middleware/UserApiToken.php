<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $tokenRecord = DB::table('user_token')
            ->where('token', $token)
            ->where('expire_at', '>', now())
            ->first();

        if (!$tokenRecord) {
            return response()->json(['message' => 'Unauthorized or token expired'], 401);
        }

        return $next($request);
    }
}