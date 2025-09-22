<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Models\v1\StockBasicFinancialMetric;

class BasicFinancialController extends Controller
{
    /**
     * Finnhub API base URL and API key.
     *
     * @var string
     */
    private string $apiBaseUrl;
    private string $apiKey;

    /**
     * Initialize API configurations.
     */
    public function __construct()
    {
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey = config('services.finnhub.key');
    }

    /**
     * Get and store basic financial metrics for a stock symbol.
     *
     * @param string $symbol
     * @return array|null
     */
    public function getBasicFinancialMetric(string $symbol): ?array
    {
        $symbol = strtoupper($symbol);
        $response = $this->fetchBasicFinancialMetricFromAPI($symbol);

        if ($response) {
            $this->storeBasicFinancialMetric($symbol, $response);
        }

        return $response;
    }

    /**
     * Get basic financial metrics for a batch of stock symbols.
     *
     * @param Request $request
     */
    public function getBasicFinancialMetricBatch(Request $request)
    {
        if ($symbol = $request->input('symbol')) {
            return response()->json($this->getBasicFinancialMetric($symbol));
        }

        return response()->json(['error' => 'Symbol is required'], 400);
    }

    /**
     * Store the retrieved basic financial metrics in the database.
     *
     * @param string $symbol
     * @param array $data
     */
    private function storeBasicFinancialMetric(string $symbol, array $data): void
    {
        $stockId = Stock::where('symbol', $symbol)->value('id');

        if ($stockId) {
            StockBasicFinancialMetric::updateStockBasicFinancialMetric($stockId, $data);
        } else {
            //Log::warning("Stock ID not found for symbol: {$symbol}");
        }
    }

    /**
     * Fetch basic financial metrics from Finnhub API.
     *
     * @param string $symbol
     * @return array|null
     */
    private function fetchBasicFinancialMetricFromAPI(string $symbol): ?array
    {
        $endpoint = '/stock/metric';
        $params = ['symbol' => $symbol, 'token' => $this->apiKey];

        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . $endpoint, $params);
            
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            //Log::error("Error fetching data for {$symbol}: " . $e->getMessage());
            return null;
        }
    }
}
