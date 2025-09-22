<?php


namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class StockUserChatbotQA
 *
 * Handles HTTP requests related to user chatbot Q&A interactions.
 *
 * @package App\Http\Controllers\v1
 */
class StockUserChatbotQA extends Controller
{
    /**
     * Save a new chatbot Q&A entry to the stock_user_chatbot_qa table.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing user_id, question, and answer.
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveChatbotQA(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'question' => 'required|string',
            'answer' => 'required|string',
        ]);

        $id = DB::table('stock_user_chatbot_qa')->insertGetId([
            'user_id' => $request->input('user_id'),
            'question' => $request->input('question'),
            'answer' => $request->input('answer'),
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Chatbot Q&A saved successfully.', 'id' => $id]);
    }

    /**
     * Retrieve all chatbot Q&A entries from the stock_user_chatbot_qa table.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllChatbotQA()
    {
        $entries = DB::table('stock_user_chatbot_qa')->get();
        return response()->json($entries);
    }

   
    public function saveChatPortfolioAnalyzerQA(Request $request)
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

        $inserted = DB::table('user_portfolio_analyzer_qa')->insertGetId([
            'user_id' => $request->input('user_id'),
            'question' => $request->input('question'),
            'answer' => $request->input('answer'),
            'created_at' => now(),
        ]);

        if($inserted === true) {
            // deduct the ai_token from the user's account
            $userId = $request->input('user_id');
            $aiToken = DB::table(' user_external_subscriptions ')->where('user_id', $userId)->value('ai_token');
            if ($aiToken > 0) {
                DB::table('user_external_subscriptions')
                    ->where('user_id', $userId)
                    ->update(['ai_token' => $aiToken - 1]);
            } else {
                return response()->json(['error' => 'Insufficient AI tokens'], 400);
            }
        }

        return response()->json(['message' => 'Q&A saved successfully.']);
    }

    public function getAllPortfolioAnalyzerQA()
    {
        $entries = DB::table('user_portfolio_analyzer_qa')->get();
        return response()->json($entries);
    }
}