<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\v1\Stock;
use App\Models\v1\StockInstitutionalOwnership;

class InstitutionalOwnershipController extends Controller
{
    /**
     * Get all institutional ownership data for a specific stock symbol.
     *
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllInstitutionalOwnershipBySymbol(string $symbol)
    {
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);
        
        // Get the stock ID
        $stockId = Stock::where('symbol', $symbol)->value('id');
        
        if (!$stockId) {
            return response()->json(['error' => 'Stock not found'], 404);
        }
        
        // Get the institutional ownership data
        $institutionalOwnership = StockInstitutionalOwnership::getInstitutionalOwnershipByStockId($stockId);
        
        return response()->json($institutionalOwnership);
    }

    /**
     * Get institutional ownership data for a specific stock symbol.
     * This method serves as an alias for getAllInstitutionalOwnershipBySymbol.
     *
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInstitutionalOwnershipBySymbol(string $symbol)
    {
        return $this->getAllInstitutionalOwnershipBySymbol($symbol);
    }

    public function getAllInstitutionalOwnership(Request $request)
    {
        $limit = $request->query('limit', 50);
        $offset = $request->query('offset', 0);
        
        $data = StockInstitutionalOwnership::getAllInstitutionalOwnership($limit, $offset);
        return response()->json($data);
    }
}