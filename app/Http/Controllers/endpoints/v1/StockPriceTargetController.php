<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use App\Models\v1\Stock;
use App\Models\v1\StockPriceTarget;

class StockPriceTargetController extends Controller
{
    /**
     * Get price target data for a specific stock symbol.
     *
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPriceTargetBySymbol(string $symbol)
    {
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);
        
        // Get the stock ID
        $stockId = Stock::where('symbol', $symbol)->value('id');
        
        if (!$stockId) {
            return response()->json(['error' => 'Stock not found'], 404);
        }
        
        // Get the price target data
        $priceTarget = StockPriceTarget::where('stock_id', $stockId)
            ->first();
            
        if (!$priceTarget) {
            return response()->json(['error' => 'Price target data not found'], 404);
        }
        
        return response()->json($priceTarget);
    }
} 