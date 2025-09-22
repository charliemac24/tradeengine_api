<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Models\v1\StockEarningsQualityAnnual;
use App\Helpers\ExecutionTimer;

class StockEarningsQualityAnnualController extends Controller
{
    /**
     * The base URL of the Finnhub API.
     *
     * @var string
     */
    private $apiBaseUrl;

    /**
     * Your Finnhub API key.
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
     * Get earnings quality annual for a stock symbol.
     *
     * @param string $symbol
     * @return array|null
     */
    public function getEarningsQualityAnnual(string $symbol)
    {
        $timer = new ExecutionTimer();
        $timer->start();

        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);

        $response = $this->fetchEarningsQualityAnnualFromAPI($symbol);

        if ($response) {
            $this->processAndSaveEarningsQualityAnnual($response);

            $timer->stop();
            $executionTime = $timer->getExecutionTime();
            
            //\Log::info("Execution time for getEarningsQualityAnnual for symbol {$symbol}: {$executionTime} seconds");
            return $response;
        } else {
            //\Log::warning("Failed to fetch earnings quality annual for symbol: $symbol");
            return null;
        }
    }

    /**
     * Get earnings quality annual for a batch of stock symbols.
     * 
     * @param Request $request
     */
    public function getEarningsQualityAnnualBatch(Request $request)
    {
        $symbol = $request->input('symbols');
        $this->getEarningsQualityAnnual($symbol);
    }

    /**
     * Process and save the earnings quality annual data.
     *
     * @param array $response
     */
    private function processAndSaveEarningsQualityAnnual($response)
    {
        $stockId = Stock::where('symbol', $response['symbol'])->value('id');
        
        if ($stockId) {
            StockEarningsQualityAnnual::updateStockEarningsQualityAnnual($stockId, $response);
        } else {
            //\Log::warning("Failed to fetch earnings quality annual for symbol: {$response['symbol']}");
        }
    }

    /**
     * Make an API request to the Finnhub service.
     * Fetch earnings quality annual for a stock symbol.
     *
     * @param string $symbol The stock symbol.
     * @return array|null The API response data or null if an error occurred.
     */
    private function fetchEarningsQualityAnnualFromAPI(string $symbol): ?array
    {
        $endpoint = '/stock/earnings-quality-score';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
        ];

        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . $endpoint, $params);

            if ($response->failed()) {
                //\Log::error('API request failed.', [
                //    'status' => $response->status(),
                //    'error' => $response->json(),
                //]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            //\Log::error('An exception occurred during API request.', [
            //    'endpoint' => $endpoint,
            //    'message' => $e->getMessage(),
            //    'trace' => $e->getTraceAsString(),
            //]);
            return null;
        }
    }
}