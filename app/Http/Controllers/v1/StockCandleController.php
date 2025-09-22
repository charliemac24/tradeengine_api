<?php
namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Models\v1\StockCandleDaily;
use App\Models\v1\StockCandleWeekly;
use App\Models\v1\StockCandleMonthly;
use App\Helpers\ExecutionTimer;
use App\Models\v1\StockPercentageDaily;
use App\Models\v1\StockPercentageWeekly;
use App\Models\v1\StockPercentageMonthly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\v1\StockProcessLog;
use Illuminate\Support\Carbon;

class StockCandleController extends Controller
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
     * StockCandleController constructor.
     */
    public function __construct()
    {
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey = config('services.finnhub.key');
    }

    /**
     * Get candlestick data for all stock symbols.
     * This one is for daily candlestick data.
     *
     * @param Request $request
     * @return void
     */
    public function getCandleStickDailyBatch(Request $request)
    {
        $symbol = $request->input('symbol');
        $this->getCandleStickDaily($symbol);
    }    

    /**
     * Get candlestick data for all stock symbols.
     * This one is for weekly candlestick data.
     *
     * @param Request $request
     * @return void
     */
    public function getCandleStickWeeklyBatch(Request $request)
    {
        $symbol = $request->input('symbol');
        $this->getCandleStickWeekly($symbol);
    }

    /**
     * Get candlestick data for all stock symbols.
     * This one is for monthly candlestick data.
     *
     * @param Request $request
     * @return void
     */
    public function getCandleStickMonthlyBatch(Request $request)
    {
        $symbol = $request->input('symbol');
        $this->getCandleStickMonthly($symbol);
    }

    /**
     * Get candlestick data for a stock symbol.
     * This one is for daily candlestick data.
     * 
     * @param string $symbol
     * @return array|null
     */
    public function getCandleStickDaily(string $symbol)
    {
        if (empty($symbol)) {
            return null;
        }

        $symbol = strtoupper($symbol);
        $resolution = 'D';

        // Use Carbon for date calculation
        $today = \Carbon\Carbon::now();
        $from = $today->copy()->subDays($today->isMonday() ? 4 : 3)->startOfDay()->timestamp;

        $response = $this->fetchStockCandleStickFromAPI($symbol, $resolution, $from);

        if ($response && isset($response['s']) && $response['s'] === 'ok') {
            $this->processAndSaveCandleSticks($symbol, $response, 'daily');
            $this->processAndSavePercentage($symbol, $response, 'daily');
            return $response;
        } else {
            // Optionally log the error here
            // \Log::warning("Failed to fetch daily candlestick data for symbol: $symbol");
            return null;
        }
    }

    /**
     * Save percentage.
     *
     * @param string $symbol
     * @param array $response
     * @param string $type
     * @return array|null
     */
    private function processAndSavePercentage(string $symbol, array $response, string $type): void
    {
        if ($response['s'] !== 'ok') {
            //Log::error("Failed to fetch percentage data for symbol: $symbol", [
            //    'response' => 'Failed to fetch percentage data for symbol: $symbol',
            //]);
            return;
        }

        $stockId = Stock::where('symbol', $symbol)->value('id');

        if ($stockId) {
            foreach ($response['t'] as $index => $timestamp) {                

                $currentPrice = $response['c'][$index] ?? null;
            $prevPrice = $response['c'][$index - 1] ?? null;

            // Skip calculation if prevPrice is null or zero
            if ($prevPrice === null || $prevPrice == 0) {
                //Log::warning("Skipping percentage calculation for symbol: $symbol at index $index due to invalid prevPrice.");
                continue;
            }

            $data = [
                'percentage' => (($currentPrice - $prevPrice) / $prevPrice) * 100,
                'closing_date' => date('Y-m-d', $timestamp) ?? null,
            ];

                try {
                    if ($type === 'daily') {
                        StockPercentageDaily::updateStockPercentage($stockId, $data);
                    } else if ($type === 'weekly') {
                        StockPercentageWeekly::updateStockPercentage($stockId, $data);
                    } else {
                        StockPercentageMonthly::updateStockPercentage($stockId, $data);
                    }
                    
                } catch (\Exception $e) {
                    //\Log::error('An exception occurred during API request.', [
                    //    'message' => $e->getMessage(),
                     //   'trace' => $e->getTraceAsString(),
                    //]);
                }
            }
        } else {
            //\Log::warning("Stock not found for symbol: $symbol");
        }
    }

    /**
     * Get candlestick data for a stock symbol.
     * This one is for weekly candlestick data.
     *
     * @param string $symbol
     * @return array|null
     */
    public function getCandleStickWeekly(string $symbol)
    {
        $timer = new ExecutionTimer();
        $timer->start();

        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);
        $resolution = 'W';
        $from = strtotime('-1 week');
        $response = $this->fetchStockCandleStickFromAPI($symbol, $resolution, $from);

        if ($response) {
            $this->processAndSaveCandleSticks($symbol, $response, 'weekly');
            $this->processAndSavePercentage($symbol, $response,'weekly');
            $timer->stop();
            $executionTime = $timer->getExecutionTime();
            //\Log::info("Execution time for getCandleStickWeekly for symbol {$symbol}: {$executionTime} seconds");
            return $response;
        } else {
            //\Log::warning("Failed to fetch candlestick data for symbol: $symbol");
            return null;
        }
    }

    /**
     * Get candlestick data for a stock symbol.
     * This one is for monthly candlestick data.
     *
     * @param string $symbol
     * @return array|null
     */
    public function getCandleStickMonthly(string $symbol)
    {
        $timer = new ExecutionTimer();
        $timer->start();

        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);
        $resolution = 'M';
        $from = strtotime('-1 month');
        $response = $this->fetchStockCandleStickFromAPI($symbol, $resolution, $from);

        if ($response) {
            $this->processAndSaveCandleSticks($symbol, $response, 'monthly');
            $this->processAndSavePercentage($symbol, $response,'monthly');
            $timer->stop();
            $executionTime = $timer->getExecutionTime();
            //\Log::info("Execution time for getCandleStickMonthly for symbol {$symbol}: {$executionTime} seconds");
            return $response;
        } else {
            //\Log::warning("Failed to fetch candlestick data for symbol: $symbol");
            return null;
        }
    }

    /**
     * Fetch the candlestick data from API.
     *
     * @param string $symbol
     * @param string $resolution
     * @param int $from
     * @return array|null
     */
    private function fetchStockCandleStickFromAPI(string $symbol, string $resolution, int $from)
    {
        $to = time();

        $endpoint = '/stock/candle';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
            'from' => $from,
            'to' => $to,
            'resolution' => $resolution
        ];
        echo $this->apiBaseUrl.$endpoint.'?'.http_build_query($params);
        return $this->makeApiRequest($endpoint, $params);
    }

    /**
     * Fetch the candlestick data from API with explicit 'to' timestamp.
     *
     * @param string $symbol
     * @param string $resolution
     * @param int $from
     * @param int $to
     * @return array|null
     */
    private function fetchStockCandleStickFromAPIWithTo(string $symbol, string $resolution, int $from, int $to)
    {
        $endpoint = '/stock/candle';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
            'from' => $from,
            'to' => $to,
            'resolution' => $resolution
        ];
        echo $this->apiBaseUrl.$endpoint.'?'.http_build_query($params);
        return $this->makeApiRequest($endpoint, $params);
    }

    /**
     * Get candlestick data for a stock symbol for the May - July range of a given year.
     * Defaults to the current year if $year is not provided.
     *
     * @param string $symbol
     * @param int|null $year
     * @return array|null
     */
    public function getCandleStickMayToJuly(string $symbol, int $year = null)
    {
        // default to current year
        $year = $year ?? date('Y');

        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);
        $resolution = 'D';

        // from May 1st to July 31st (inclusive) for the provided year
        $from = strtotime("{$year}-05-01 00:00:00");
        $to = strtotime("{$year}-07-31 23:59:59");

        $response = $this->fetchStockCandleStickFromAPIWithTo($symbol, $resolution, $from, $to);

        if ($response) {
            // Save and process as daily data
            $this->processAndSaveCandleSticks($symbol, $response, 'daily');
            $this->processAndSavePercentage($symbol, $response, 'daily');
            return $response;
        } else {
            //
            return null;
        }
    }

    /**
     * Make an API request to the Finnhub API.
     *
     * @param string $endpoint
     * @param array $params
     * @return array|null
     */
    private function makeApiRequest(string $endpoint, array $params)
    {
        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . $endpoint, $params);

            if ($response->successful()) {
                return $response->json();
            }

            //Log::error("Failed to fetch data from {$endpoint}.", [
            //    'status' => $response->status(),
            //    'body' => $response->body(),
            //]);

            return null;
        } catch (\Exception $e) {
            //Log::error("Exception occurred while fetching data from {$endpoint}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Process and save the candlestick data.
     *
     * @param string $symbol
     * @param array $response
     * @param string $type
     * @return void
     */
    private function processAndSaveCandleSticks(string $symbol, array $response, string $type): void
    {
        if ($response['s'] !== 'ok') {
            //Log::error("Failed to fetch candlestick data for symbol: $symbol", [
            //    'response' => $response,
            //]);
            return;
        }

        $stockId = Stock::where('symbol', $symbol)->value('id');

        if ($stockId) {
            foreach ($response['t'] as $index => $timestamp) {
                $data = [
                    'c' => $response['c'][$index] ?? null,
                    'h' => $response['h'][$index] ?? null,
                    'l' => $response['l'][$index] ?? null,
                    'o' => $response['o'][$index] ?? null,
                    's' => $response['s'] ?? null,
                    't' => $timestamp ?? null,
                    'v' => $response['v'][$index] ?? null,
                ];

                try {
                    if ($type === 'daily') {
                        StockCandleDaily::updateStockCandle($stockId, $data);
                    } else if ($type === 'weekly') {
                        StockCandleWeekly::updateStockCandle($stockId, $data);
                    } else {
                        StockCandleMonthly::updateStockCandle($stockId, $data);
                    }
                } catch (\Exception $e) {
                    //\Log::error('An exception occurred during API request.', [
                    //    'message' => $e->getMessage(),
                    //    'trace' => $e->getTraceAsString(),
                    //]);
                }
            }
        } else {
            //\Log::warning("Stock not found for symbol: $symbol");
        }
    }

    /**
     * Store or update a stock price prediction.
     *
     * Expects JSON body with keys:
     *  - date
     *  - stock
     *  - closing_price_today
     *  - pred_tom_ta
     *  - pred_tom_his
     *  - pred_5_days_ta
     *  - pred_5_days_his
     */
    public function savePricePrediction(Request $request)
    {
        // 1. Validate incoming JSON
        $data = $request->validate([
            'date'                  => 'required|date',
            'stock'                 => 'required|string|max:10',
            'closing_price_today'   => 'required|numeric',
            'response'           => 'nullable|string'
        ]);

        // 2. Prepare the “where” keys and the full data array
        $keys = [
            'date'  => $data['date'],
            'stock' => $data['stock'],
        ];
        $values = array_merge(
            $data,
            ['created_at' => Carbon::now()]
        );

        // 3. Upsert (insert or update) the record
        DB::table('stock_price_prediction')
            ->updateOrInsert($keys, $values);

        // 4. Return a JSON response
        return response()->json([
            'message' => 'Stock price prediction saved successfully.',
        ], 201);
    }

    public function displayPricePrediction()
    {
        $records = DB::table('stock_price_prediction')->get();

        return response()->json($records);
    }
}