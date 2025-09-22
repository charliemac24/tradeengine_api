<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Helpers\ExecutionTimer;
use App\Models\v1\StockSocialSentiment;

class StockSocialSentimentController extends Controller
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
     * Get social sentiment for a stock symbol.
     *
     * @param string $symbol
     * @return bool|null
     */
    public function getSocialSentiments(string $symbol)
    {
        $timer = new ExecutionTimer();
        $timer->start();
        
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);

        $response = $this->fetchSocialSentimentFromAPI($symbol);

        if ($response) {
            $this->processAndSaveSocialSentiment($response,$symbol);
            $timer->stop();
            $executionTime = $timer->getExecutionTime();
            //\Log::info("Execution time for getSocialSentiments for symbol {$symbol}: {$executionTime} seconds");
            return $response;
        } else {
            //\Log::warning("Failed to fetch social sentiment for symbol: $symbol");
            return null;
        }
    }

    /**
     * Get social sentiment for all stock symbols.
     *
     * @param Request $request
     * @return void
     */
    public function getSocialSentimentsBatch(Request $request)
    {
        $symbol = $request->input('symbol');
        $this->getSocialSentiments($symbol);
    }

    /**
     * Make an API request to the Finnhub service.
     * Fetch social sentiments for a stock symbol.
     *
     * @param string $endpoint The stock symbol.
     * @return array|null The API response data or null if an error occurred.
     */
    private function fetchSocialSentimentFromAPI(string $symbol): ?array
    {
        $from = date('Y-m-d');
        $to = date('Y-m-d');

        $endpoint = '/stock/social-sentiment';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
            'from' => $from,
            'to' => $to,
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
            ////    'message' => $e->getMessage(),
            //    'trace' => $e->getTraceAsString(),
            //]);
            return null;
        }
    }

    /**
     * Process and save the stock social sentiment.
     *
     * @param array $response
     * @param string $symbol
     */
    public function processAndSaveSocialSentiment($response,$symbol)
    {
        $stockId = Stock::where('symbol', $symbol)->value('id');
        $sentiments = $response['data'];
        if ($stockId) {
            foreach($sentiments as $sentiment) {
                if ( !empty($sentiment['atTime']) ) {
                    StockSocialSentiment::updateStockSocialSentiments(
                        $stockId,
                        $sentiment
                    );
                }                
            }
        } else {
            //\Log::warning("Stock not found for symbol: " . $symbol);
            return null;
        }
    }

    /**
     * Retrieve social sentiment records from stock_social_sentiments.
     * Optional query params:
     *  - symbol: filter by stock symbol
     *  - from: YYYY-MM-DD (inclusive)
     *  - to: YYYY-MM-DD (inclusive)
     *  - limit: integer limit (default 50)
     *  - page: page number for offset-based paging (default 1)
     *
     * Uses the stock_social_sentiments.at_time field for date filtering.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSocialSentimentList(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $page = max(1, (int) $request->query('page', 1));
        $from = $request->query('from');
        $to = $request->query('to');
        $symbol = $request->query('symbol');

        $query = StockSocialSentiment::query()->orderBy('at_time', 'desc');

        if ($symbol) {
            $symbol = strtoupper($symbol);
            $stockId = Stock::where('symbol', $symbol)->value('id');
            if (! $stockId) {
                return response()->json(['data' => [], 'message' => 'Symbol not found'], 200);
            }
            $query->where('stock_id', $stockId);
        }

        if ($from) {
            try {
                $fromDate = \Carbon\Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
                $query->where('at_time', '>=', $fromDate->toDateTimeString());
            } catch (\Exception $e) {
                return response()->json(['error' => 'Invalid from date format, expected YYYY-MM-DD'], 422);
            }
        }

        if ($to) {
            try {
                $toDate = \Carbon\Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
                $query->where('at_time', '<=', $toDate->toDateTimeString());
            } catch (\Exception $e) {
                return response()->json(['error' => 'Invalid to date format, expected YYYY-MM-DD'], 422);
            }
        }

        $results = $query->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $results]);
    }
}
