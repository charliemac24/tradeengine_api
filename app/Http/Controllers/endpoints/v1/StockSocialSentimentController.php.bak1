<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\v1\Stock;
use App\Models\v1\StockSocialSentiment;

class StockSocialSentimentController extends Controller
{
    /**
     * Get all social sentiment data for a specific stock symbol
     * 
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSocialSentimentBySymbol(string $symbol)
    {
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);

        // Find the stock ID from the stock symbol
        $stock = Stock::where('symbol', $symbol)->first();

        if ($stock) {
            $data = StockSocialSentiment::where('stock_id', $stock->id)
                ->select('*')
                ->orderBy('at_time', 'desc')
                ->get();
            return response()->json($data);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Get all social sentiment data with pagination
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllStockSocialSentiments(Request $request)
    {
        $limit = $request->query('limit', 50);
        $offset = $request->query('offset', 0);
        
        $data = StockSocialSentiment::getAllStockSocialSentiments($limit, $offset);
        return response()->json($data);
    }
}