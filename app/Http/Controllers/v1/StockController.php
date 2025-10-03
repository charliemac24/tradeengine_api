<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\v1\Stock;
use App\Models\v1\StockInfo;
use App\Models\v1\StockMarketCap;
use App\Helpers\ExecutionTimer;

/**
 * Class StockController
 *
 * Controller for managing stock-related operations, including fetching stock symbols, profiles, and market caps.
 */
class StockController extends Controller
{
    /**
     * The base URL of the Finnhub API.
     *
     * @var string
     */
    private string $apiBaseUrl;

    /**
     * The API key for the Finnhub service.
     *
     * @var string
     */
    private string $apiKey;

    /**
     * StockController constructor.
     * Initializes API base URL and API key from configuration.
     */
    public function __construct()
    {
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey = config('services.finnhub.key');
    }

    /**
     * Fetch and store US stock symbols from the Finnhub API.
     *
     * @return void
     */
    public function getStockSymbols(): void
    {
        $endpoint = '/stock/symbol';
        $params = [
            'exchange' => 'US',
            'token' => $this->apiKey,
        ];

        $response = $this->makeApiRequest($endpoint, $params);

        if ($response) {
            $this->processAndSaveStockSymbols($response);
        }
    }

    /**
     * Save fetched stock symbols into the database.
     *
     * @param array $stockSymbols List of stock symbols fetched from the API.
     * @return void
     */
    private function processAndSaveStockSymbols(array $stockSymbols): void
    {
        foreach ($stockSymbols as $item) {
            try {
                Stock::insertIntoStockSymbols($item['symbol']);
            } catch (\Exception $e) {
                //Log::error('Failed to insert stock symbol.', [
                //    'symbol' => $item['symbol'],
                //    'message' => $e->getMessage(),
                //]);
            }
        }
    }

    /**
     * Fetch and store a stock's profile data from the Finnhub API.
     *
     * @param string $symbol The stock symbol to fetch the profile for.
     * @return void
     */
    public function getStockSymbolProfile(string $symbol): void
    {
        $symbol = strtoupper($symbol);

        $endpoint = '/stock/profile';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
        ];

        $response = $this->makeApiRequest($endpoint, $params);

        if ($response) {
            $this->processAndSaveStockInfo($response);
        }
    }

    public function getStockSymbolProfilesBatch(Request $request){

        $symbol = $request->input('symbol');
        $endpoint = '/stock/profile';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
        ];

        $response = $this->makeApiRequest($endpoint, $params);
        
        
        if ($response) {
            $this->processAndSaveStockInfo($response);
        }
    }

    /**
     * Save stock profile information into the database.
     *
     * @param array $stockSymbol Stock profile data fetched from the API.
     * @return void
     */
    private function processAndSaveStockInfo(array $responseArray): void
    {
        $stockId = Stock::where('symbol', $responseArray['ticker'])->value('id');

        //if (($stockId && (isset($responseArray['country']) && ($responseArray['country'] === 'CA' || $responseArray['country'] === 'US'))) || $responseArray['currency']==='USD') {
         
        if( $responseArray['currency'] === 'CAD' || $responseArray['currency'] === 'USD') {
           
            StockInfo::updateStockSymbolProfile([
                'stock_id' => $stockId,
                    'description' => $responseArray['description'],
                    'address' => $responseArray['address'],
                    'city' => $responseArray['city'],
                    'sector' => $responseArray['gsector'],
                    'industry' => $responseArray['finnhubIndustry'],
                    'website' => $responseArray['weburl'],
                    'name' => $responseArray['name'],
                    'currency' => $responseArray['currency'],
                    'stocks_logo' => $responseArray['logo'],
                    'market_cap' => $responseArray['marketCapitalization'] * 1000000,
                    'share_outstanding' => $responseArray['shareOutstanding']
            ]);
        }
    }

    public function updateStockCompanyName(Request $request): void
    {
        $symbol = strtoupper($request->input('symbol'));
        
        $endpoint = '/stock/profile';
        $params = [
            'token' => $this->apiKey,
            'symbol'=> $symbol
        ];

        $response = $this->makeApiRequest($endpoint, $params);

        if ($response) {

            $stockId = Stock::where('symbol', $response['ticker'])->value('id');

            StockInfo::updateStockSymbolName([
                'stock_id' => $stockId,
                'name' => $response['name'] ?? ''
            ]);
        }
    }

    /**
     * Fetch and update the market capitalization of a stock symbol.
     *
     * @param string $symbol The stock symbol to fetch the market capitalization for.
     * @return void
     */
    public function getStockMarketCap(string $symbol): void
    {
        $symbol = strtoupper($symbol);

        $endpoint = '/stock/profile';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
        ];

        $response = $this->makeApiRequest($endpoint, $params);

        if ($response) {
            $this->saveProcessStockMarketCap($response, $symbol);
        }
    }
    
    /**
     * Fetch and update the market capitalization of multiple stock symbols.
     *
     * @param Request $request
     * @return void
     */
    public function getStockMarketCapBatch(Request $request): void
    {
        $symbol = $request->input('symbol');
        $this->getStockMarketCap($symbol);
    }

    /**
     * Save market capitalization data into the database.
     *
     * @param array $response The API response data.
     * @param string $symbol The stock symbol.
     * @return void
     */
    private function saveProcessStockMarketCap(array $response, string $symbol): void
    {
        $stockId = Stock::where('symbol', $symbol)->value('id');

        if ($stockId) {
            StockMarketCap::updateMarketCapitalization($stockId, $response['marketCapitalization'], $response['name']);
        }
    }

    /**
     * Make an API request to the Finnhub service.
     *
     * @param string $endpoint The API endpoint to request.
     * @param array $params Query parameters for the API request.
     * @return array|null The API response data or null if an error occurred.
     */
    private function makeApiRequest(string $endpoint, array $params): ?array
    {
        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . $endpoint, $params);

            if ($response->failed()) {
               // Log::error('API request failed.', [
               //     'endpoint' => $endpoint,
               //     'status' => $response->status(),
               //     'error' => $response->json(),
               // ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            //Log::error('An exception occurred during API request.', [
            //    'endpoint' => $endpoint,
            //    'message' => $e->getMessage(),
            //]);
            return null;
        }
    }

    public function updateCompanyNamesForMissingStocks(): void
    {
        // Retrieve all stock symbols without a company name
        $symbolsWithoutCompanyName = StockInfo::join('stock_symbols', 'stock_symbol_info.stock_id', '=', 'stock_symbols.id')
            ->whereNull('stock_symbol_info.company_name')
            ->orWhere('stock_symbol_info.company_name', '=', '')
            ->limit(500)
            ->pluck('stock_symbols.symbol')
            ->toArray();

        if (empty($symbolsWithoutCompanyName)) {
            $this->info("No stocks without company names found.");
            return;
        }

        //$this->info("Processing " . count($symbolsWithoutCompanyName) . " stocks without company names.");

        foreach ($symbolsWithoutCompanyName as $symbol) {
            //$this->info("Processing stock: {$symbol}");

            try {
                // Call the /stock/profile endpoint to get the company name
                $endpoint = '/stock/profile';
                $params = [
                    'symbol' => $symbol,
                    'token' => $this->apiKey,
                ];

                $response = $this->makeApiRequest($endpoint, $params);

                if ($response && isset($response['name'])) {
                    // Update the company_name in the stock_symbol_info table
                    $stockId = Stock::where('symbol', $symbol)->value('id');

                    if ($stockId) {
                        StockInfo::where('stock_id', $stockId)->update([
                            'company_name' => $response['name'],
                        ]);
                        //$this->info("Updated company name for stock: {$symbol}");
                    } else {
                        //$this->warning("Stock ID not found for symbol: {$symbol}");
                    }
                } else {
                    //$this->warning("No valid response for stock: {$symbol}");
                }
            } catch (\Exception $e) {
                //$this->error("Error processing stock {$symbol}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Fetch and update the market capitalization in bulk.
     *
     * @return void
     */
    public function getStockMarketCapBulk(): void
    {
        
        $symbols = Stock::getStockSymbolsWithInfo()->pluck('symbol')->toArray();
        $idx=0;
        foreach($symbols as $symbol) {
            
            $idx++;

            $endpoint = '/stock/profile';
            $params = [
                'symbol' => $symbol,
                'token' => $this->apiKey,
            ];
    
            $response = $this->makeApiRequest($endpoint, $params);
    
            if ($response) {
                $this->saveProcessStockMarketCap($response, $symbol);
            }
            
            if($idx == 100) {
                sleep(60);
            }
        }
    }

    /**
     * Update the stocks_by_market_cap table with the top 2000 stocks based on market cap.
     * Replaces all existing content in the table with the current top 2000 stocks.
     *
     * @return \Illuminate\Http\JsonResponse A JSON response indicating success or failure.
     */
    public function updateTop2000StocksByMarketCap(): \Illuminate\Http\JsonResponse
    {
        try {
            // Step 1: Retrieve stock symbols from stock_symbol_info ordered by market_cap (highest to lowest)
            $topStocks = DB::table('stock_symbols')
                ->join('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')
                ->select('stock_symbols.symbol', 'stock_symbol_info.market_cap')
                ->whereNotNull('stock_symbol_info.market_cap')
                ->where('stock_symbol_info.market_cap', '>', 0) // Only include stocks with positive market cap
                ->orderBy('stock_symbol_info.market_cap', 'desc')
                ->limit(2000) // Step 2: Cut the result to 2000 items only
                ->get();

            if ($topStocks->isEmpty()) {
                return response()->json([
                    'error' => 'No stocks found with valid market cap data',
                    'message' => 'The stock_symbol_info table contains no stocks with positive market cap values'
                ], 404);
            }

            // Start a database transaction for data consistency
            DB::beginTransaction();

            // Step 3: Update existing records in stocks_by_market_cap table instead of truncating
            $rank = 1;
            $idx = 1; // Start from 1 since IDs typically start from 1
            $updatedRecords = 0;
            
            foreach ($topStocks as $stock) {
                // Update the record where id = $idx
                $updated = DB::table('stocks_by_market_cap')
                    ->where('id', $idx)
                    ->update([
                        'symbol' => $stock->symbol,
                        'market_cap' => $stock->market_cap,
                        'rank' => $rank,
                        'processed' => 1,
                        'updated_at' => now(),
                    ]);
                
                if ($updated) {
                    $updatedRecords++;
                }
                
                $rank++;
                $idx++;
            }

            // Commit the transaction
            DB::commit();

            // Prepare summary statistics
            $totalStocksProcessed = count($topStocks);
            $highestMarketCap = $topStocks->first()->market_cap;
            $lowestMarketCap = $topStocks->last()->market_cap;

            return response()->json([
                'success' => true,
                'message' => 'Successfully updated top 2000 stocks by market cap',
                'data' => [
                    'total_stocks_processed' => $totalStocksProcessed,
                    'total_records_updated' => $updatedRecords,
                    'highest_market_cap' => $highestMarketCap,
                    'lowest_market_cap' => $lowestMarketCap,
                    'highest_market_cap_symbol' => $topStocks->first()->symbol,
                    'lowest_market_cap_symbol' => $topStocks->last()->symbol,
                    'updated_at' => now()->toDateTimeString()
                ],
                'sample_data' => [
                    'top_5' => $topStocks->take(5)->map(function($stock, $index) {
                        return [
                            'rank' => $index + 1,
                            'symbol' => $stock->symbol,
                            'market_cap' => $stock->market_cap
                        ];
                    }),
                    'bottom_5' => $topStocks->slice(-5)->map(function($stock, $index) use ($totalStocksProcessed) {
                        return [
                            'rank' => $totalStocksProcessed - 4 + $index,
                            'symbol' => $stock->symbol,
                            'market_cap' => $stock->market_cap
                        ];
                    })->values()
                ]
            ]);

        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            DB::rollback();
            
            Log::error('Failed to update top 2000 stocks by market cap: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to update stocks by market cap',
                'message' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ], 500);
        }
    }
}
