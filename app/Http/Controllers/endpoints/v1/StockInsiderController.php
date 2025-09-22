<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use App\Models\v1\Stock;
use App\Models\v1\StockInsider;
use Illuminate\Http\Request;

class StockInsiderController extends Controller
{
    /**
     * Get insider transactions for a specific stock symbol.
     *
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInsiderBySymbol(string $symbol)
    {
        // Convert symbol to uppercase
        $symbol = strtoupper($symbol);
        
        // Get the stock ID
        $stockId = Stock::where('symbol', $symbol)->value('id');
        
        if (!$stockId) {
            return response()->json([
                'error' => 'Stock not found',
                'message' => "No stock found with symbol: {$symbol}"
            ], 404);
        }
        
        // Get the insider transactions
        $insiders = StockInsider::where('stock_id', $stockId)
            ->orderBy('trans_date', 'desc')
            ->get();
        
        if ($insiders->isEmpty()) {
            return response()->json([
                'error' => 'Insider data not found',
                'message' => "No insider transactions available for stock: {$symbol}"
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $insiders
        ]);
    }

    /**
     * Get all insider transactions with pagination.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllInsiders(Request $request)
    {
        // Validate and sanitize input
        $limit = min(max((int)$request->query('limit', 50), 1), 100); // Between 1 and 100
        $offset = max((int)$request->query('offset', 0), 0); // Non-negative
        
        $insiders = StockInsider::join('stock_symbols', 'stock_insiders.stock_id', '=', 'stock_symbols.id')
            ->orderBy('stock_insiders.trans_date', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get(['stock_insiders.*', 'stock_symbols.symbol']);
        
        return response()->json([
            'success' => true,
            'data' => $insiders,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($insiders)
            ]
        ]);
    }
} 