<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use App\Models\v1\Stock;
use App\Models\v1\StockRecommendationTrends;
use Illuminate\Http\Request;

class StockRecommendationController extends Controller
{
    /**
     * Get recommendation trends for a specific stock symbol.
     *
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStockRecommendationBySymbol(string $symbol)
    {
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);
        
        // Get the stock ID
        $stockId = Stock::where('symbol', $symbol)->value('id');
        
        if (!$stockId) {
            return response()->json(['error' => 'Stock not found'], 404);
        }
        
        // Get the recommendation trends data
        $recommendations = StockRecommendationTrends::getRecommendationTrendsByStockId($stockId);
        
        if (empty($recommendations)) {
            return response()->json(['error' => 'Recommendation data not found'], 404);
        }
        
        return response()->json($recommendations);
    }

    /**
     * Get all stock recommendation trends with pagination.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllStockRecommendations(Request $request)
    {
        $limit = $request->query('limit', 50);
        $offset = $request->query('offset', 0);
        
        $recommendations = StockRecommendationTrends::getAllRecommendationTrends($limit, $offset);
        
        return response()->json($recommendations);
    }
} 