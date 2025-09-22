<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Models\v1\StockPriceTarget;

class PriceTargetController extends Controller
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
     * PriceTargetController constructor.
     */
    public function __construct()
    {
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey = config('services.finnhub.key');
    }

    /**
     * Get price target for a stock symbol.
     *
     * @param string $symbol The stock symbol.
     * @return bool True if successfully processed, false otherwise.
     */
    public function getPriceTarget(string $symbol): bool
    {
        $symbol = strtoupper($symbol);
        $response = $this->fetchPriceTargetFromAPI($symbol);

        if ($response) {
            $this->processAndSavePriceTarget($response);
            return true;
        }

        //Log::warning("Failed to fetch price target for symbol: $symbol");
        return false;
    }

    /**
     * Get price target for a batch of stock symbols.
     *
     * @param Request $request The HTTP request containing stock symbols.
     * @return void
     */
    public function getPriceTargetBatch(Request $request): void
    {
        $symbol = $request->input('symbol');
        if (!$symbol) {
            //Log::warning('No symbol provided in batch request.');
            return;
        }
        $this->getPriceTarget($symbol);
    }

    /**
     * Process and save the price target data.
     *
     * @param array $response The API response data.
     * @return void
     */
    private function processAndSavePriceTarget(array $response): void
    {
        $stockId = Stock::where('symbol', $response['symbol'])->value('id');

        if ($stockId) {
            StockPriceTarget::updateStockPriceTarget($stockId, $response);
        } else {
            //Log::warning("Failed to process and save price target for symbol: {$response['symbol']}");
        }
    }

    /**
     * Fetch price target for a stock symbol from Finnhub API.
     *
     * @param string $symbol The stock symbol.
     * @return array|null The API response data or null if an error occurred.
     */
    private function fetchPriceTargetFromAPI(string $symbol): ?array
    {
        $endpoint = '/stock/price-target';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
        ];

        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . $endpoint, $params);

            if ($response->failed()) {
                //Log::error('API request failed.', [
                //    'endpoint' => $endpoint,
                //    'status' => $response->status(),
                //    'error' => $response->json(),
                //]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
           // Log::error('An exception occurred during API request.', [
            //    'endpoint' => $endpoint,
            //    'message' => $e->getMessage(),
            //    'trace' => $e->getTraceAsString(),
           // ]);
            return null;
        }
    }
}