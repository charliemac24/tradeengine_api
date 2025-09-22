<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserRestrictionController extends Controller
{
    /**
     * Get all feature access levels for the user's tier.
     */
    public function getUserFeatureAccess(Request $request)
    {
        $user = $request->user(); // Assumes you are using Laravel Auth
        $tierId = $user->tier_id;

        $features = DB::table('feature_access_levels')
            ->join('features', 'feature_access_levels.feature_id', '=', 'features.id')
            ->where('feature_access_levels.tier_id', $tierId)
            ->select('features.code', 'features.name', 'feature_access_levels.access_level', 'feature_access_levels.details')
            ->get();

            
        return response()->json(['tier_id' => $tierId, 'features' => $features]);
    }

    /**
     * Check if the user has access to a specific feature and level.
     */
    public function hasFeatureAccess(Request $request, $featureCode, $requiredLevel = 'basic')
    {
        $user = $request->user();
        $tierId = $user->tier_id;

        $access = DB::table('feature_access_levels')
            ->join('features', 'feature_access_levels.feature_id', '=', 'features.id')
            ->where('feature_access_levels.tier_id', $tierId)
            ->where('features.code', $featureCode)
            ->select('feature_access_levels.access_level')
            ->first();

        if (!$access) {
            return response()->json(['access' => false, 'reason' => 'Feature not found for this tier'], 403);
        }

        // You can expand this logic to compare levels if needed
        $hasAccess = $access->access_level !== 'none';

        return response()->json(['access' => $hasAccess, 'level' => $access->access_level]);
    }
}