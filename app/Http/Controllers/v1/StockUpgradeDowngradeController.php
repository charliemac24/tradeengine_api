<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Helpers\ExecutionTimer;
use App\Models\v1\StockUpgradeDowngrade;

class StockUpgradeDowngradeController extends Controller
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
     * Get stock upgrade downgrade for a stock symbol.
     *
     * @param string $symbol
     * @return bool|null
     */
    public function getStockUpgradeDowngrade(string $symbol)
    {
        $timer = new ExecutionTimer();
        $timer->start();
        
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);

        $response = $this->fetchStockUpgradeDowngradeFromAPI($symbol);

        if ($response) {
            $this->processAndSaveStockUpgradeDowngrade($response,$symbol);
            $timer->stop();
            $executionTime = $timer->getExecutionTime();
            //\Log::info("Execution time for getStockUpgradeDowngrade for symbol {$symbol}: {$executionTime} seconds");
            return $response;
        } else {
            //\Log::warning("Failed to fetch stock upgrade downgrade for symbol: $symbol");
            return null;
        }
    }
    
    /**
     * Get stock upgrade downgrade for all stock symbols.
     *
     * @param Request $request
     * @return void
     */
    public function getStockUpgradeDowngradeBatch(Request $request)
    {
        $symbol = $request->input('symbol');
        $this->getStockUpgradeDowngrade($symbol);
    }

    /**
     * Make an API request to the Finnhub service.
     * Fetch stock upgrade downgrade for a stock symbol.
     *
     * @param string $endpoint The stock symbol.
     * @return array|null The API response data or null if an error occurred.
     */
    private function fetchStockUpgradeDowngradeFromAPI(string $symbol): ?array
    {
        $from = date('Y-m-d');
        $to = date('Y-m-d');

        $endpoint = '/stock/upgrade-downgrade';
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
            //    'message' => $e->getMessage(),
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
    public function processAndSaveStockUpgradeDowngrade($response,$symbol)
    {
        $stockId = Stock::where('symbol', $symbol)->value('id');
        if ($stockId) {
            foreach($response as $data) {
                if ( !empty($data['gradeTime']) ) {
                    StockUpgradeDowngrade::updateStockUpgradeDowngrade(
                        $stockId,
                        $data
                    );
                }                
            }
        } else {
            //\Log::warning("Stock not found for symbol: " . $symbol);
            return null;
        }
    }
}
