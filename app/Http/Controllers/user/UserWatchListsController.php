<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class UserWatchListsController extends Controller
{
    /**
     * Delete a stock from the user's watchlist.
     *
     * @param Request $request The HTTP request containing the stock ID to delete.
     * @return \Illuminate\Http\JsonResponse A JSON response indicating success or failure.
     */
    public function deleteStockFromWatchlist(Request $request)
    {
        $request->validate([
            'stock_id' => 'required|exists:stock_symbols,id',
        ]);

        $userId = 1; // Auth::id(); // Get the authenticated user's ID

        if (!$userId) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        // Delete the stock from the user's watchlist
        $deleted = DB::table('user_watchlists')
            ->where('user_id', $userId)
            ->where('stock_id', $request->input('stock_id'))
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Stock removed from watchlist successfully']);
        } else {
            return response()->json(['error' => 'Stock not found in watchlist'], 404);
        }
    }

    /**
     * Create a new watchlist for the user.
     *
     * @param Request $request The HTTP request containing the watchlist name.
     * @return \Illuminate\Http\JsonResponse A JSON response indicating success or failure.
     */
    public function createWatchlist(Request $request)
    {
        $request->validate([
            'watchlist_name' => 'required|string|max:255',
        ]);

        $userId = 1; // Auth::id(); // Get the authenticated user's ID

        if (!$userId) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        // Insert the new watchlist into the user_watchlists table
        DB::table('user_watchlists')->insert([
            'user_id' => $userId,
            'watchlist_name' => $request->input('watchlist_name'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Watchlist created successfully']);
    }

    /**
     * Add a stock to a specific watchlist.
     *
     * @param Request $request The HTTP request containing the watchlist ID and stock ID.
     * @return \Illuminate\Http\JsonResponse A JSON response indicating success or failure.
     */
    public function addStockToSpecificWatchlist(Request $request)
    {
        $request->validate([
            'watchlist_id' => 'required|exists:user_watchlists,id',
            'stock_id' => 'required|exists:stock_symbols,id',
        ]);

        $userId = 1; // Auth::id(); // Get the authenticated user's ID

        if (!$userId) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        // Insert the stock into the user_stock_watchlists table
        DB::table('user_stock_watchlists')->insert([
            'watchlist_id' => $request->input('watchlist_id'),
            'stock_id' => $request->input('stock_id'),
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Stock added to the selected watchlist successfully']);
    }

    /**
     * Delete a watchlist and its related records in the user_stock_watchlists table.
     *
     * @param Request $request The HTTP request containing the watchlist ID to delete.
     * @return \Illuminate\Http\JsonResponse A JSON response indicating success or failure.
     */
    public function deleteWatchlist(Request $request)
    {
        $request->validate([
            'watchlist_id' => 'required|exists:user_watchlists,id',
        ]);

        $userId = 1; // Auth::id(); // Get the authenticated user's ID

        if (!$userId) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        // Delete related records from user_stock_watchlists
        DB::table('user_stock_watchlists')
            ->where('watchlist_id', $request->input('watchlist_id'))
            ->delete();

        // Delete the watchlist from user_watchlists
        $deleted = DB::table('user_watchlists')
            ->where('id', $request->input('watchlist_id'))
            ->where('user_id', $userId)
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Watchlist and related stocks removed successfully']);
        } else {
            return response()->json(['error' => 'Watchlist not found or could not be deleted'], 404);
        }
    }
}