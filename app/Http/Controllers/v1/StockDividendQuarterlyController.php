<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Models\v1\StockDividendQuarterly;

class StockDividendQuarterlyController extends Controller
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
     * Get stock dividend quarterly data for a stock symbol.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStockDividendQuarterly(Request $request)
    {
        $symbol = strtoupper($request->input('symbol'));
        $from = date('Y-m-d', strtotime('-5 days'));
        $to = date('Y-m-d');
        
        $response = $this->fetchStockDividendQuarterlyFromAPI($symbol, $from, $to);

        if ($response) {
            $this->processAndSaveStockDividendQuarterly($response, $symbol);
            return response()->json($response);
        } else {
            //Log::warning("Failed to fetch stock dividend quarterly for symbol: $symbol");
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    /**
     * Make an API request to the Finnhub service.
     * Fetch stock dividend quarterly data for a stock symbol.
     *
     * @param string $symbol The stock symbol.
     * @param string $from The start date.
     * @param string $to The end date.
     * @return array|null The API response data or null if an error occurred.
     */
    private function fetchStockDividendQuarterlyFromAPI(string $symbol, string $from, string $to): ?array
    {
        $endpoint = '/stock/dividend';
        $params = [
            'symbol' => $symbol,
            'from' => $from,
            'to' => $to,
            'token' => $this->apiKey,
        ];

        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . $endpoint, $params);

            if ($response->failed()) {
               // Log::error('API request failed.', [
                //    'status' => $response->status(),
                //    'error' => $response->json(),
                //]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            //Log::error('An exception occurred during API request.', [
            //    'endpoint' => $endpoint,
            //    'message' => $e->getMessage(),
            //    'trace' => $e->getTraceAsString(),
            //]);
            return null;
        }
    }

    /**
     * Process and save the stock dividend quarterly data.
     *
     * @param array $response
     * @param string $symbol
     */
    private function processAndSaveStockDividendQuarterly(array $response, string $symbol)
    {
        $stockId = Stock::where('symbol', $symbol)->value('id');

        if ($stockId) {
            $totalAmount = 0;
            $fromDate = null;
            $toDate = null;

            foreach ($response as $index => $dividend) {
                $totalAmount += $dividend['amount'];
                if ($index === 0) {
                    $fromDate = $dividend['date'];
                }
                if ($index === count($response) - 1) {
                    $toDate = $dividend['date'];
                }
            }

            $avgDividend = $totalAmount / count($response);

            StockDividendQuarterly::updateStockDividendQuarterly(
                $stockId,
                [
                    'avg_dividend' => $avgDividend,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'amount'=>$totalAmount,
                    'paydate'=>$response[0]['payDate']
                ]
            );
        } else {
          //  Log::warning("Stock not found for symbol: " . $symbol);
        }
    }
}