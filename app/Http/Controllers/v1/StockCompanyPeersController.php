<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Models\v1\StockCompanyPeers;

class StockCompanyPeersController extends Controller
{
    /**
     * The base URL of the API.
     *
     * @var string
     */
    private $apiBaseUrl;

    /**
     * Your API key.
     *
     * @var string
     */
    private $apiKey;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey = config('services.finnhub.key');
    }

    /**
     * Fetch and save stock peers for a given stock symbol.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchAndSaveStockPeers(Request $request)
    {
        $symbol = strtoupper($request->input('symbol'));

        // Validate the symbol
        if (empty($symbol)) {
            return response()->json(['error' => 'Symbol is required'], 400);
        }

        // Fetch stock peers from the API
        $response = $this->fetchStockPeersFromAPI($symbol);

        if ($response && is_array($response)) {
            // Get the stock ID for the given symbol
            $stockId = Stock::where('symbol', $symbol)->value('id');

            if (!$stockId) {
                return response()->json(['error' => "Stock ID not found for symbol: $symbol"], 404);
            }

            // Save each peer symbol to the database
            foreach ($response as $peerSymbol) {
                try {
                    StockCompanyPeers::updateOrCreatePeer($stockId, $peerSymbol);
                } catch (\Exception $e) {
                   // Log::error("Error saving peer symbol: $peerSymbol for stock ID: $stockId", [
                   //     'message' => $e->getMessage(),
                   // ]);
                }
            }

            return response()->json(['message' => 'Stock peers saved successfully']);
        } else {
            return response()->json(['error' => 'Failed to fetch stock peers'], 500);
        }
    }

    /**
     * Fetch stock peers from the API.
     *
     * @param string $symbol
     * @return array|null
     */
    private function fetchStockPeersFromAPI(string $symbol): ?array
    {
        $endpoint = '/stock/peers';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
        ];

        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . $endpoint, $params);

            if ($response->successful()) {
                return $response->json();
            } else {
                //Log::error('API request failed.', [
               //     'status' => $response->status(),
               //     'error' => $response->json(),
                ///]);
                return null;
            }
        } catch (\Exception $e) {
            //Log::error('An exception occurred during API request.', [
            //    'endpoint' => $endpoint,
            //    'message' => $e->getMessage(),
            //]);
            return null;
        }
    }
}