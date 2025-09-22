<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Models\v1\StockCompanyNews;
use App\Models\v1\StockTopNews;
use App\Helpers\ExecutionTimer;

class CompanyNewsController extends Controller
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
     * Get company news for a stock symbol.
     *
     * @param string $symbol
     * @return array|null
     */
    public function getCompanyNews(string $symbol)
    {      
       

        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);

        $response = $this->fetchCompanyNewsFromAPI($symbol);

        if ($response) {
            $this->processAndSaveCompanyNews($response,$symbol);
            
           
            
            //\Log::info("Execution time for getCompanyNews for symbol {$symbol}: {$executionTime} seconds");
            return $response;
        } else {
            //\Log::warning("Failed to fetch company news for symbol: $symbol");  
            return null;        
        }
    }

    /**
     * Get company news for all stock symbols.
     * 
     * @param Request $request
     */
    public function getCompanyNewsBatch(Request $request)
    {
        $timer = new ExecutionTimer();
        $timer->start();

        $symbol = $request->input('symbol');
        
        $this->getCompanyNews($symbol);

        $timer->stop();
        $executionTime = $timer->getExecutionTime();

        //\Log::info("Execution time for getCompanyNewsBatch: {$executionTime} seconds");
    }

    /**
     * Process and save the company news data.
     *
     * @param array $response
     * @param string $symbol
     */
    private function processAndSaveCompanyNews($response,$symbol)
    {
        $stockId = Stock::where('symbol', $symbol)->value('id');

        if ($stockId) {

            foreach( $response as $data ) {
                try {
                    StockCompanyNews::updateStockCompanyNews($stockId, $data);
                } catch (\Exception $e) {
                    //\Log::error('An exception occurred during API request.', [
                    //    'message' => $e->getMessage(),
                    //    'trace' => $e->getTraceAsString(),
                    //]);
                }                     
            }
        } else {
            //\Log::warning("Stock not found for symbol: " . $response['symbol']);
            return null;
        }
    }

    /**
     * Make an API request to the Finnhub service.
     * Get company news for a stock symbol.
     *
     * @param string $symbol The stock symbol.
     * @return array|null The API response data or null if an error occurred.
     */
    private function fetchCompanyNewsFromAPI(string $symbol): ?array
    {
        $dayOfWeek = (int) date('N'); // 1 = Monday
        if ($dayOfWeek === 1) {
            // Monday: get data starting from last Friday
            $from = date('Y-m-d', strtotime('last friday'));
        } else {
            // Other days: last 2 days
            $from = date('Y-m-d', strtotime('-2 days'));
        }
        $to = date('Y-m-d');

        $endpoint = '/company-news';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
            'from'  => $from,
            'to'    => $to
        ];

        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . $endpoint, $params);
            if ($response->failed()) {
                return null;
            }
            return $response->json();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fetch top news from Finnhub and save to stock_top_news table.
     */
    public function fetchAndSaveTopNews()
    {
        $url = 'https://finnhub.io/api/v1/news?category=top-news&token=ctukd71r01qg98tdggqgctukd71r01qg98tdggr0';
        try {
            $response = Http::timeout(15)->get($url);
            if ($response->failed()) {
                return response()->json(['error' => 'Failed to fetch top news'], 500);
            }
            $newsList = $response->json();
            if (!is_array($newsList)) {
                return response()->json(['error' => 'Invalid response format'], 500);
            }
            foreach ($newsList as $news) {
                $newsId = $news['id'] ?? null;
                if (!$newsId) continue;
                $headline = $news['headline'] ?? null;
                $dateTime = isset($news['datetime']) ? date('Y-m-d H:i:s', $news['datetime']) : null;
                $imageUrl = $news['image'] ?? null;
                $related = $news['related'] ?? null;
                $source = $news['source'] ?? null;
                $summary = $news['summary'] ?? null;
                $urlField = $news['url'] ?? null;
                // Try to find stock_id by related symbol, else null
                $stockId = null;
                if (!empty($related)) {
                    $symbol = strtoupper(explode(',', $related)[0]);
                    $stockId = \App\Models\v1\Stock::where('symbol', $symbol)->value('id');
                }
                \App\Models\v1\StockTopNews::updateOrCreate(
                    ['news_id' => $newsId],
                    [
                        'stock_id' => $stockId,
                        'category' => 'Top News',
                        'date_time' => $dateTime,
                        'headline' => $headline,
                        'image_url' => $imageUrl,
                        'related' => $related,
                        'source' => $source,
                        'summary' => $summary,
                        'url' => $urlField,
                    ]
                );
            }
            return response()->json(['message' => 'Top news fetched and saved successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Retrieve top news records from stock_top_news table.
     * Optional query params:
     *  - symbol: filter by stock symbol
     *  - limit: integer limit (default 50)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTopNews(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $symbol = $request->query('symbol');

        $query = StockTopNews::query()->orderBy('date_time', 'desc');

        if ($symbol) {
            $symbol = strtoupper($symbol);
            $stockId = Stock::where('symbol', $symbol)->value('id');
            if (! $stockId) {
                return response()->json(['data' => [], 'message' => 'Symbol not found'], 200);
            }
            $query->where('stock_id', $stockId);
        }

        $results = $query->limit($limit)->get();

        return response()->json(['data' => $results]);
    }

    /**
     * Retrieve company news records from stock_company_news table.
     * Optional query params:
     *  - symbol: filter by stock symbol
     *  - from: YYYY-MM-DD (inclusive)
     *  - to: YYYY-MM-DD (inclusive)
     *  - limit: integer limit (default 50)
     *  - page: page number for offset-based paging (default 1)
     *
     * Uses the stock_company_news.date_time field for date filtering.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCompanyNewsList(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $page = max(1, (int) $request->query('page', 1));
        $from = $request->query('from');
        $to = $request->query('to');
        $symbol = $request->query('symbol');

        $query = StockCompanyNews::query()->orderBy('date_time', 'desc');

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
                $query->where('date_time', '>=', $fromDate->toDateTimeString());
            } catch (\Exception $e) {
                return response()->json(['error' => 'Invalid from date format, expected YYYY-MM-DD'], 422);
            }
        }

        if ($to) {
            try {
                $toDate = \Carbon\Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
                $query->where('date_time', '<=', $toDate->toDateTimeString());
            } catch (\Exception $e) {
                return response()->json(['error' => 'Invalid to date format, expected YYYY-MM-DD'], 422);
            }
        }

        $results = $query->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $results]);
    }
}
