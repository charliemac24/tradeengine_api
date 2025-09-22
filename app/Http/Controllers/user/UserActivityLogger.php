<?php
// filepath: c:\Users\Charlie\Projects\docker-trendseeker-server-laravel\application\app\Http\Controllers\user\UserActivityLogger.php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserActivityLogger extends Controller
{
    /**
     * Log a user activity.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function log(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'activity' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255',
            'details' => 'nullable|string',
        ]);

        DB::table('user_activity_logger')->insert([
            'user_id' => $request->input('user_id'),
            'activity' => $request->input('activity'),
            'domain' => $request->input('domain', 'General'),
            'details' => $request->input('details'),
        ]);

        return response()->json(['message' => 'Activity logged successfully.']);
    }
}