<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Models\v1\StockInsider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class StockInsiderController extends Controller
{
    /**
     * The base URL of the Finnhub API.
     *
     * @var string
     */
    private string $apiBaseUrl;

    /**
     * Your Finnhub API key.
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
     * Fetch insider sentiment from Finnhub and save to stock_insider_sentiment table.
     * Query params: symbol (required), from (YYYY-MM-DD), to (YYYY-MM-DD)
     */
    public function fetchAndSaveInsiderSentiment(Request $request)
    {
        $symbol = $request->query('symbol');
        $from = $request->query('from');
        $to = $request->query('to');

        if (! $symbol) {
            return response()->json(['error' => 'symbol is required'], 422);
        }

        $symbol = strtoupper($symbol);

        // default window if not provided
        if (! $from) {
            $from = date('Y-m-d', strtotime('-5 days'));
        }
        if (! $to) {
            $to = date('Y-m-d');
        }

        $endpoint = '/stock/insider-sentiment';
        $params = [
            'symbol' => $symbol,
            'from' => $from,
            'to' => $to,
            'token' => $this->apiKey,
        ];

        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . $endpoint, $params);
            if ($response->failed()) {
                return response()->json(['error' => 'Failed to fetch data from Finnhub', 'status' => $response->status()], 500);
            }

            $json = $response->json();
            $list = $json['data'] ?? [];
            if (! is_array($list)) {
                return response()->json(['error' => 'Invalid response format from Finnhub'], 500);
            }

            $rows = [];
            $now = now();
            foreach ($list as $item) {
                $year = isset($item['year']) ? (int) $item['year'] : null;
                $month = isset($item['month']) ? (int) $item['month'] : null;
                $change = $item['change'] ?? null;
                $mspr = $item['mspr'] ?? null;

                if ($year === null || $month === null) continue;

                $rows[] = [
                    'symbol' => $symbol,
                    'd_year' => $year,
                    'd_month' => $month,
                    'd_change' => $change,
                    'mspr' => $mspr,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($rows) > 0) {
                // upsert rows: unique by symbol + d_year + d_month
                DB::table('stock_insider_sentiment')->upsert($rows, ['symbol', 'd_year', 'd_month'], ['d_change', 'mspr', 'updated_at']);
            }

            return response()->json(['message' => 'Insider sentiment fetched and saved', 'count' => count($rows)]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Retrieve insider sentiment records from stock_insider_sentiment.
     * Optional query params:
     *  - symbol: filter by stock symbol
     *  - from: YYYY (inclusive)
     *  - to: YYYY (inclusive)
     *  - limit: integer limit (default 50)
     *  - page: page number for offset-based paging (default 1)
     *
     * Filtering is applied against the d_year field.
     */
    public function getInsiderSentimentList(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $page = max(1, (int) $request->query('page', 1));
        $symbol = $request->query('symbol');
        $from = $request->query('from');
        $to = $request->query('to');

        $query = DB::table('stock_insider_sentiment')->orderBy('d_year', 'desc');

        if ($symbol) {
            $query->where('symbol', strtoupper($symbol));
        }

        if ($from) {
            if (! preg_match('/^\d{4}$/', $from)) {
                return response()->json(['error' => 'Invalid from year format, expected YYYY'], 422);
            }
            $query->where('d_year', '>=', (int) $from);
        }

        if ($to) {
            if (! preg_match('/^\d{4}$/', $to)) {
                return response()->json(['error' => 'Invalid to year format, expected YYYY'], 422);
            }
            $query->where('d_year', '<=', (int) $to);
        }

        //$results = $query->skip(($page - 1) * $limit)->take($limit)->get();
        $results = $query->get();
        return response()->json(['data' => $results]);
    }

    /**
     * Get stock insider transactions for a given stock symbol.
     *
     * @param string $symbol The stock symbol.
     * @return array|null The API response data or null if an error occurred.
     */
    public function getStockInsider(string $symbol): ?array
    {
        $symbol = strtoupper($symbol);
        $response = $this->fetchInsiderFromAPI($symbol);
        echo "<pre>";
        print_r($response);
        echo "</pre>";

        if ($response) {
            $this->processAndSaveStockInsider($response, $symbol);
            return $response;
        }

        //Log::warning("Failed to fetch stock insider transactions for symbol: $symbol");
        return null;
    }

    /**
     * Get stock insider transactions for a batch of stock symbols.
     *
     * @param Request $request The HTTP request instance.
     * @return void
     */
    public function getStockInsiderBatch(Request $request): void
    {
        $symbol = $request->input('symbol');
        if ($symbol) {
            $this->getStockInsider($symbol);
        }
    }

    /**
     * Process and save stock insider transaction data.
     *
     * @param array $response The API response data.
     * @param string $symbol The stock symbol.
     * @return void
     */
    private function processAndSaveStockInsider(array $response, string $symbol): void
    {
        $stockId = Stock::where('symbol', $symbol)->value('id');

        if (!$stockId) {
           // Log::warning("Stock not found for symbol: $symbol");
            return;
        }

        foreach ($response['data'] as $insider) {
            if (in_array($insider['transactionCode'], ['P', 'S'])) {
                StockInsider::updateStockInsider($stockId, $insider);
            }
        }
    }

    /**
     * Fetch insider transactions from the Finnhub API.
     *
     * @param string $symbol The stock symbol.
     * @return array|null The API response data or null if an error occurred.
     */
    private function fetchInsiderFromAPI(string $symbol, ?string $from = null, ?string $to = null): ?array
    {
        // Default: from = yesterday, to = today
        $from = $from ?? date('Y-m-d', strtotime('-1 day'));
        $to = $to ?? date('Y-m-d');

        $endpoint = '/stock/insider-transactions';
        $params = [
            'symbol' => $symbol,
            'from' => $from,
            'to' => $to,
            'token' => $this->apiKey,
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
     * Retrieve insider transactions from stock_insiders table.
     * Optional query params:
     *  - symbol: filter by stock symbol
     *  - from: YYYY-MM-DD (inclusive)
     *  - to: YYYY-MM-DD (inclusive)
     *  - limit: integer limit (default 50)
     *  - page: page number for offset-based paging (default 1)
     *
     * The method will attempt to auto-detect a date/time column on the insiders table
     * (common candidates: transaction_date, date_time, at_time, date).
     */
    public function getInsidersList(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $page = max(1, (int) $request->query('page', 1));
        $from = $request->query('from');
        $to = $request->query('to');
        $symbol = $request->query('symbol');

        $model = new StockInsider();
        $table = $model->getTable();

    // Candidate date columns in order of preference (insiders table uses trans_date / filling_date)
    $candidates = ['trans_date', 'filling_date', 'transaction_date', 'date_time', 'at_time', 'date', 'transactionDate', 'reported_date'];
        $dateColumn = null;
        foreach ($candidates as $c) {
            if (Schema::hasColumn($table, $c)) {
                $dateColumn = $c;
                break;
            }
        }

        if (! $dateColumn) {
            return response()->json(['error' => "Unable to locate a date/time column on table {$table}."], 500);
        }

        $query = StockInsider::query()->orderBy($dateColumn, 'desc');

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
                $query->where($dateColumn, '>=', $fromDate->toDateTimeString());
            } catch (\Exception $e) {
                return response()->json(['error' => 'Invalid from date format, expected YYYY-MM-DD'], 422);
            }
        }

        if ($to) {
            try {
                $toDate = \Carbon\Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
                $query->where($dateColumn, '<=', $toDate->toDateTimeString());
            } catch (\Exception $e) {
                return response()->json(['error' => 'Invalid to date format, expected YYYY-MM-DD'], 422);
            }
        }

        $results = $query->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $results]);
    }

    /**
     * Retrieve stock insider records where filling_date is today.
     */
    public function getTodayStockInsiders()
    {
        $carbonToday = now();
        //$today = now()->subDay()->format('Y-m-d');
        if ($carbonToday->isMonday()) {
            $today = $carbonToday->subDays(3)->format('Y-m-d');
        } else {
            $today = $carbonToday->subDays(1)->format('Y-m-d');
        }

        $results = \App\Models\v1\StockInsider::whereDate('filling_date', $today)->get();

        return response()->json(['data' => $results]);
    }

    /**
     * Retrieve stock insider records where filling_date is today.
     */
    public function getTodayStockInsiders2()
    {
        $carbonToday = now();
        // If today is Monday, get last Friday
        if ($carbonToday->isMonday()) {
            $today = $carbonToday->subDays(3)->format('Y-m-d');
        } else {
            $today = $carbonToday->format('Y-m-d');
        }

        $results = \App\Models\v1\StockInsider::whereDate('filling_date', $today)->get();

        // If no results, step back 1 day and try again
        if ($results->isEmpty()) {
            $yesterday = \Carbon\Carbon::createFromFormat('Y-m-d', $today)->subDay()->format('Y-m-d');
            $results = \App\Models\v1\StockInsider::whereDate('filling_date', $yesterday)->get();
        }

        return response()->json(['data' => $results]);
    }
}
