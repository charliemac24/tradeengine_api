<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\v1\Stock;
use App\Models\v1\StockCandleDaily;

class StockCandleDailyController extends Controller
{
    /**
     * Retrieve all data from the StockCandleDaily model for a given stock symbol.
     *
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllStockCandleDaily(string $symbol)
    {
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);

        // Find the stock ID from the stock symbol
        $stock = Stock::where('symbol', $symbol)->first();

        if ($stock) {
            $data = StockCandleDaily::getStockCandleDailyByStockId($stock->id);
            return response()->json($data);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Retrieve stock candle daily data by symbol and optional date range.
     *
     * Query parameters:
     *  - start_date (Y-m-d)
     *  - end_date   (Y-m-d)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySymbolAndDateRange(Request $request, string $symbol)
    {
        // Normalize symbol
        $symbol = strtoupper($symbol);

        // Optional filters
        $startDate = $request->query('start_date');  // e.g. /api/.../AAPL?start_date=2025-01-01
        $endDate   = $request->query('end_date');

        // Call the model method
        $results = StockCandleDaily::getBySymbolAndDateRange($symbol, $startDate, $endDate);

        // If no records and symbol doesn't exist, return 404
        if ($results->isEmpty() && ! Stock::where('symbol', $symbol)->exists()) {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }

        return response()->json($results);
    }
}
