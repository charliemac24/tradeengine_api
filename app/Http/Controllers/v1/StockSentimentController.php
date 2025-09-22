<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Helpers\ExecutionTimer;
use App\Models\v1\StockSentiment;

class StockSentimentController extends Controller
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
     * Get sentiment for a stock symbol.
     *
     * @param string $symbol
     * @return bool|null
     */
    public function getSentiments(string $symbol)
    {
        $timer = new ExecutionTimer();
        $timer->start();
        
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);

        $response = $this->fetchSentimentFromAPI($symbol);

        if ($response) {
            $this->processAndSaveSentiment($response);
            $timer->stop();
            $executionTime = $timer->getExecutionTime();
            //\Log::info("Execution time for getSentiments for symbol {$symbol}: {$executionTime} seconds");
            return $response;
        } else {
            //\Log::warning("Failed to fetch sentiment for symbol: $symbol");
            return null;
        }
    }

    /**
     * Get sentiment for all stock symbols.
     *
     * @param Request $request
     * @return void
     */
    public function getSentimentsBatch(Request $request)
    {
        $symbol = $request->input('symbol');
        $this->getSentiments($symbol);
    }

    /**
     * Make an API request to the Finnhub service.
     * Fetch news sentiments for a stock symbol.
     *
     * @param string $endpoint The stock symbol.
     * @return array|null The API response data or null if an error occurred.
     */
    private function fetchSentimentFromAPI(string $symbol): ?array
    {
        $endpoint = '/news-sentiment';
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

    /**
     * Process and save the sentiments data.
     *
     * @param array $response
     */
    private function processAndSaveSentiment($response)
    {
        $stockId = Stock::where('symbol', $response['symbol'])->value('id');

        if ($stockId) {
            $sentiment = $response['sentiment']['bearishPercent'] > $response['sentiment']['bullishPercent'] ? 'bearish' : 'bullish';
            $sentiment_label = "";
            if ($sentiment == 'bearish') {
                $sentiment_label = $response['sentiment']['bearishPercent'] > 0.75 ? 'Very Bearish' : 'Bearish';
            } else {
                $sentiment_label = $response['sentiment']['bullishPercent'] > 0.75 ? 'Very Bullish' : 'Bullish';
            }
            StockSentiment::updateStockNewsSentiments($stockId, $response, $sentiment_label);
        }else{
            //\Log::warning("Failed to fetch news sentiment for symbol: $symbol");
        }
    }
    
}
