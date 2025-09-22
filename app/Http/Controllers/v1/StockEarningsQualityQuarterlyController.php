<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Models\v1\StockEarningsQualityQuarterly;

class StockEarningsQualityQuarterlyController extends Controller
{
    /**
     * Finnhub API base URL.
     *
     * @var string
     */
    private string $apiBaseUrl;

    /**
     * Finnhub API key.
     *
     * @var string
     */
    private string $apiKey;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey = config('services.finnhub.key');
    }

    /**
     * Retrieve earnings quality quarterly data for a stock symbol.
     *
     * @param string $symbol Stock symbol.
     * @return array|null API response data or null if an error occurred.
     */
    public function getEarningsQualityQuarterly(string $symbol): ?array
    {
        $symbol = strtoupper($symbol);
        $response = $this->fetchEarningsQualityQuarterlyFromAPI($symbol);
        if ($response) {
            $this->processAndSaveEarningsQualityQuarterly($response);
            return $response;
        }

       // Log::warning("Failed to fetch earnings quality quarterly for symbol: $symbol");
        return null;
    }

    /**
     * Retrieve earnings quality quarterly data for multiple stock symbols.
     *
     * @param Request $request HTTP request containing stock symbols.
     * @return void
     */
    public function getEarningsQualityQuarterlyBatch(Request $request): void
    {
        $symbol = $request->input('symbol');        
        $this->getEarningsQualityQuarterly($symbol);
    }

    /**
     * Process and save earnings quality quarterly data.
     *
     * @param array $response API response data.
     * @return void
     */
    private function processAndSaveEarningsQualityQuarterly(array $response): void
    {
        $stockId = Stock::where('symbol', $response['symbol'])->value('id');

        if ($stockId) {
            StockEarningsQualityQuarterly::updateStockEarningsQualityQuarterly($stockId, $response);
        } else {
            //Log::warning("Stock not found for symbol: {$response['symbol']}");
        }
    }

    /**
     * Fetch earnings quality quarterly data from the Finnhub API.
     *
     * @param string $symbol Stock symbol.
     * @return array|null API response data or null if an error occurred.
     */
    private function fetchEarningsQualityQuarterlyFromAPI(string $symbol): ?array
    {
        $endpoint = '/stock/earnings-quality-score';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
        ];
        
        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . $endpoint, $params);

            if ($response->failed()) {
                //Log::error('API request failed.', [
                //    'symbol' => $symbol,
                //    'status' => $response->status(),
                //    'error' => $response->json(),
                //]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
           // Log::error('Exception during API request.', [
            //    'symbol' => $symbol,
            //    'message' => $e->getMessage(),
            //]);
            return null;
        }
    }
}
