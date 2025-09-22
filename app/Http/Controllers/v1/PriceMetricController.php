<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Models\v1\StockPriceMetric;

/**
 * Class PriceMetricController
 * 
 * Handles fetching and storing stock price metrics from the Finnhub API.
 */
class PriceMetricController extends Controller
{
    /**
     * The base URL of the Finnhub API.
     *
     * @var string
     */
    private string $apiBaseUrl;

    /**
     * The Finnhub API key.
     *
     * @var string
     */
    private string $apiKey;

    /**
     * Constructor.
     * 
     * Initializes API base URL and API key from the configuration.
     */
    public function __construct()
    {
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey = config('services.finnhub.key');
    }

    /**
     * Fetches price metric data for a given stock symbol and stores it.
     * 
     * @param string $symbol The stock symbol (e.g., "AAPL").
     * @return array|null The price metric data or null if an error occurs.
     */
    public function getPriceMetric(string $symbol): ?array
    {
        $symbol = strtoupper($symbol);
        $response = $this->fetchPriceMetricFromAPI($symbol);

        if ($response) {
            $this->processAndSavePriceMetric($response);
            return $response;
        }

        //Log::warning("Failed to fetch price metric for symbol: $symbol");
        return null;
    }

    /**
     * Fetches price metric data for a batch of stock symbols.
     * 
     * @param Request $request The HTTP request containing the stock symbols.
     * @return void
     */
    public function getPriceMetricBatch(Request $request): void
    {
        $symbol = $request->input('symbol');
        if ($symbol) {
            $this->getPriceMetric($symbol);
        } else {
            //Log::warning("No symbol provided for price metric batch request.");
        }
    }

    /**
     * Processes and saves the price metric data in the database.
     * 
     * @param array $response The API response data.
     * @return void
     */
    private function processAndSavePriceMetric(array $response): void
    {
        $stockId = Stock::where('symbol', $response['symbol'])->value('id');

        if ($stockId) {
            StockPriceMetric::updateStockPriceMetric($stockId, $response);
        } else {
            //Log::warning("Stock not found for symbol: {$response['symbol']}");
        }
    }

    /**
     * Fetches price metric data from the Finnhub API.
     * 
     * @param string $symbol The stock symbol (e.g., "AAPL").
     * @return array|null The API response data or null if an error occurs.
     */
    private function fetchPriceMetricFromAPI(string $symbol): ?array
    {
        $endpoint = '/stock/price-metric';
        $params = [
            'symbol' => $symbol,
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
     * Retrieves financial metrics for a given stock symbol from existing database tables.
     *
     * @param string $symbol The stock symbol (e.g., "AAPL").
     * @return array|null The financial metrics data or null if not found.
     */
    public function getFinancialMetrics(string $symbol): ?array
    {
        $symbol = strtoupper($symbol);

        // Get stock_id from Stock table
        $stock = \App\Models\v1\Stock::where('symbol', $symbol)->first();
        if (!$stock) {
            return null;
        }
        $stockId = $stock->id;

        // Latest Price & Open & Volume & Day's Range from stock_candle_daily
        $latestCandle = \DB::table('stock_candle_daily')
            ->where('stock_id', $stockId)
            ->orderByDesc('ts')
            ->first();

        // Market Cap & Beta from stock_basic_financials_metric
        $basicMetrics = \DB::table('stock_basic_financials_metric')
            ->where('stock_id', $stockId)
            ->first();

        // Day's Range (low - high) from latest stock_candle_daily
        $daysRange = $latestCandle
            ? number_format($latestCandle->low_price, 2) . ' - ' . number_format($latestCandle->high_price, 2)
            : null;

        // Earnings Date from stock_earnings_calendar (latest cal_date)
        $earnings = \DB::table('stock_earnings_calendar')
            ->where('stock_id', $stockId)
            ->orderByDesc('cal_date')
            ->first();

        // 52 Week Range from stock_price_metrics
        $priceMetrics = \DB::table('stock_price_metrics')
            ->where('stock_id', $stockId)
            ->first();
        $weekRange = $priceMetrics
            ? number_format($priceMetrics->data_52_week_low, 2) . ' - ' . number_format($priceMetrics->data_52_week_high, 2)
            : null;

        // Ex-Dividend Date from stock_dividend_quarterly (latest avg_dividend)
        $dividend = \DB::table('stock_dividend_quarterly')
            ->where('stock_id', $stockId)
            ->orderByDesc('to_date')
            ->first();

        // 1y Target Est from stock_price_target
        $target = \DB::table('stock_price_target')
            ->where('stock_id', $stockId)
            ->first();

        return [
            'latest_price'      => $latestCandle ? $latestCandle->close_price : null,
            'market_cap'        => $basicMetrics ? $basicMetrics->market_cap : null,
            'open'              => $latestCandle ? $latestCandle->open_price : null,
            'beta_5y_monthly'   => $basicMetrics ? $basicMetrics->beta : null,
            'days_range'        => $daysRange,
            'earnings_date'     => $earnings ? $earnings->cal_date : null,
            '52_week_range'     => $weekRange,
            'ex_dividend_date'  => $dividend ? $dividend->to_date : null,
            'volume'            => $latestCandle ? $latestCandle->volume : null,
            'target_est_1y'     => $target ? $target->target_median : null,
        ];
    }
}
