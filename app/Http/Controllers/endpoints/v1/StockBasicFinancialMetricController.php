<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use App\Models\v1\Stock;
use App\Models\v1\StockBasicFinancialMetric;

class StockBasicFinancialMetricController extends Controller
{
    /**
     * Get all basic financial metrics for a specific stock symbol.
     *
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllBasicFinancialMetrics(string $symbol)
    {
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);
        
        // Get the stock ID
        $stockId = Stock::where('symbol', $symbol)->value('id');
        
        if (!$stockId) {
            return response()->json(['error' => 'Stock not found'], 404);
        }
        
        // Get the basic financial metrics data
        $basicFinancialMetrics = StockBasicFinancialMetric::getBasicFinancialMetricsByStockId($stockId);
        
        return response()->json($basicFinancialMetrics);
    }
} 