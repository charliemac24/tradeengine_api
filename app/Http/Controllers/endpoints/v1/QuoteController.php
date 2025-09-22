<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\v1\Stock;
use App\Models\v1\StockQuote;

class QuoteController extends Controller
{
    /**
     * Retrieve all data from the StockQuote model for a given stock symbol.
     *
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllStockQuotes(string $symbol)
    {
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);

        // Find the stock ID from the stock symbol
        $stock = Stock::where('symbol', $symbol)->first();

        if ($stock) {
            $quotes = StockQuote::getQuotesByStockId($stock->id);
            return response()->json($quotes);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }
}
