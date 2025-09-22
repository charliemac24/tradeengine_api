<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\v1\Stock;
use App\Models\v1\StockBasicFinancialMetric;

class BasicFinancialController extends Controller
{
    /**
     * Retrieve all data from the StockBasicFinancialMetric model for a given stock symbol.
     *
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllBasicFinancialMetrics(string $symbol)
    {
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);

        // Find the stock ID from the stock symbol
        $stock = Stock::where('symbol', $symbol)->first();

        if ($stock) {
            $metrics = StockBasicFinancialMetric::getBasicFinancialMetricByStockId($stock->id);
            return response()->json($metrics);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }
}
