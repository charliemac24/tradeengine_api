<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAnalysisQAController extends Controller
{
    public function saveUserStockQuery(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'question' => 'required|string',
            'answer' => 'required|string',
        ]);

        // Check if the user has sufficient AI tokens
        $aiToken = DB::table('user_external_subscriptions')
            ->where('user_id', $request->input('user_id'))
            ->value('ai_token');

        if ($aiToken <= 0) {
            return response()->json(['error' => 'Insufficient AI tokens'], 400);   
        }

        $inserted = DB::table('user_stock_query')->insert([
            'user_id' => $request->input('user_id'),
            'question' => $request->input('question'),
            'answer' => $request->input('answer'),
            'created_at' => now(),
        ]);

        if($inserted === true) {
            // deduct the ai_token from the user's account
            $userId = $request->input('user_id');
            $aiToken = DB::table('user_external_subscriptions')->where('user_id', $userId)->value('ai_token');
            if ($aiToken > 0) {
                DB::table('user_external_subscriptions')
                    ->where('user_id', $userId)
                    ->update(['ai_token' => $aiToken - 1]);
            } else {
                return response()->json(['error' => 'Insufficient AI tokens'], 400);
            }
        }

        return response()->json(['message' => 'Query saved successfully.']);
    }

    public function getAllUserStockQueries()
    {
        $queries = DB::table('user_stock_query')->orderBy('created_at', 'desc')->get();
        return response()->json(['queries' => $queries]);
    }
}