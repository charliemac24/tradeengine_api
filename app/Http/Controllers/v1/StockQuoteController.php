<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Models\v1\StockQuote;
use App\Helpers\ExecutionTimer;

class StockQuoteController extends Controller
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
     * Get price quote for a stock symbol.
     *
     * @param string $symbol
     * @return array|null
     */
    public function getStockQuote(string $symbol)
    {
        $timer = new ExecutionTimer();
        $timer->start();

        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);

        $response = $this->fetchStockQuoteFromAPI($symbol);

        if ($response) {
            $this->processAndSaveStockQuote($response,$symbol);
            $timer->stop();
            $executionTime = $timer->getExecutionTime();
            //\Log::info("Execution time for getStockQuote for symbol {$symbol}: {$executionTime} seconds");
            return $response;
        } else {
            //\Log::warning("Failed to fetch stock quote for symbol: $symbol");
            return null;
        }
    }

    /**
     * Get stock quote for a batch of stock symbols.
     * 
     * @param Request $request
     */
    public function getStockQuoteBatch(Request $request)
    {
        $timer = new ExecutionTimer();
        $timer->start();

        $symbol = $request->input('symbol');
        $this->getStockQuote($symbol);
    }

    /**
     * Process and save the stock quote data.
     *
     * @param array $response
     * @param string $symbol
     */
    public function processAndSaveStockQuote($response,$symbol)
    {
        $stockId = Stock::where('symbol', $symbol)->value('id');

        if ($stockId) {
            StockQuote::updateStockQuote($stockId, $response);
        } else {
            //\Log::warning("Stock not found for symbol: " . $response['symbol']);
        }
    }

    /**
     * Make an API request to the Finnhub service.
     * Fetch stock quote for a stock symbol.
     *
     * @param string $symbol The stock symbol.
     * @return array|null The API response data or null if an error occurred.
     */
    private function fetchStockQuoteFromAPI(string $symbol): ?array
    {
        $endpoint = '/quote';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
        ];

        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . $endpoint, $params);

            if ($response->failed()) {
                //\Log::error('API request failed.', [
                //    'endpoint' => $endpoint,
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
