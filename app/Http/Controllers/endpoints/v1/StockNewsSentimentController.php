<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use App\Models\v1\Stock;
use App\Models\v1\StockNewsSentiment;
use Illuminate\Http\Request;

class StockNewsSentimentController extends Controller
{
    /**
     * Get news sentiment data for a specific stock symbol.
     * Returns sentiment data including bearish/bullish percentages, overall sentiment, and news score.
     *
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNewsSentimentBySymbol(string $symbol)
    {
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);
        
        // Get the stock ID
        $stockId = Stock::where('symbol', $symbol)->value('id');
        
        if (!$stockId) {
            return response()->json([
                'error' => 'Stock not found',
                'message' => "No stock found with symbol: {$symbol}"
            ], 404);
        }
        
        // Get the news sentiment data
        $sentiment = StockNewsSentiment::getNewsSentimentByStockId($stockId);
        
        if (!$sentiment) {
            return response()->json([
                'error' => 'News sentiment data not found',
                'message' => "No sentiment data available for stock: {$symbol}"
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $sentiment
        ]);
    }

    /**
     * Get all stock news sentiments with pagination.
     * Returns a list of stock sentiments ordered by most recently updated.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllNewsSentiments(Request $request)
    {
        // Validate and sanitize input
        $limit = min(max((int)$request->query('limit', 50), 1), 100); // Between 1 and 100
        $offset = max((int)$request->query('offset', 0), 0); // Non-negative
        
        $sentiments = StockNewsSentiment::getAllNewsSentiments($limit, $offset);
        
        return response()->json([
            'success' => true,
            'data' => $sentiments,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($sentiments)
            ]
        ]);
    }
} 