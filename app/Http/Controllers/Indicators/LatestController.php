<?php

namespace App\Http\Controllers\Indicators;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class LatestController extends Controller
{
    private $apiBaseUrl;
    private $apiKey;

    public function __construct()
    {
        // Initialize any required properties or dependencies here
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey = config('services.finnhub.key');
    }
    
    public function fetchIndicatorData(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string',
            'indicator' => 'required|string',
            'resolution' => 'nullable|string',
            'from' => 'nullable|integer',
            'to' => 'nullable|integer',
            'timeperiod' => 'nullable|integer|min:1|max:100',
        ]);

        $symbol = $request->input('symbol');
        $indicator = $request->input('indicator');
        $resolution = $request->input('resolution', 'D');
        $from = $request->input('from', strtotime('-30 days'));
        $to = $request->input('to', time());
        $timeperiod = $request->input('timeperiod', 3);

        $response = Http::timeout(15)->get($this->apiBaseUrl . '/stock/candle', [
            'symbol' => $symbol,
            'indicator' => $indicator,
            'resolution' => $resolution,
            'from' => $from,
            'to' => $to,
            'token' => $this->apiKey,
            'timeperiod' => $timeperiod,
        ]);
        echo "<pre>";
        print_r($response->json());
        echo "</pre>";
        die();
        return $response->json();
    }

    /**
     * Get stock indicators for a specific symbol.
     *
     * @param string $symbol The stock symbol to retrieve indicators for.
     * @return \Illuminate\Http\JsonResponse A JSON response containing the stock indicators.
     */
    public function getStockIndicators(string $symbol): \Illuminate\Http\JsonResponse
    {
        // Get the stock_id for the given symbol
        $stockId = DB::table('stock_symbols')
            ->where('symbol', $symbol)
            ->value('id');

        if (!$stockId) {
            return response()->json(['error' => 'Stock symbol not found: ' . $symbol], 404);
        }

        // Get the latest stock indicators for this stock
        $indicators = DB::table('stock_indicators')
            ->where('stock_id', $stockId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$indicators) {
            return response()->json(['error' => 'No indicators found for symbol: ' . $symbol], 404);
        }

        // Include the symbol in the response
        $response = [
            'symbol' => $symbol,
            'stock_id' => $stockId,
            'indicators' => $indicators
        ];

        return response()->json($response);
    }
}