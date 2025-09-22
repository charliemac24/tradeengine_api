<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Models\v1\StockInstitutionalOwnership;

/**
 * Class InstitutionalOwnershipController
 * 
 * Handles fetching and storing institutional ownership data from the Finnhub API.
 */
class InstitutionalOwnershipController extends Controller
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
     * Fetches institutional ownership data for a given stock symbol and stores it.
     * 
     * @param string $symbol The stock symbol (e.g., "AAPL").
     * @param string $from Start date in YYYY-MM-DD format.
     * @param string $to End date in YYYY-MM-DD format.
     * @return array|null The ownership data or null if an error occurs.
     */
    public function getInstitutionalOwnership(string $symbol, string $from, string $to): ?array
    {
        $symbol = strtoupper($symbol);
        $response = $this->fetchInstitutionalOwnershipFromAPI($symbol, $from, $to);
        
        if ($response) {
            $this->processAndSaveInstitutionalOwnership($response, $symbol);
            return $response;
        }

        //Log::warning("Failed to fetch institutional ownership for symbol: $symbol");
        return null;
    }

    /**
     * Fetches and stores institutional ownership data for a batch of stock symbols.
     * 
     * @param Request $request The HTTP request containing stock symbols.
     * @return void
     */
    public function getInstitutionalOwnershipBatch(Request $request): void
    {
        $from = date('Y-m-d');
        $to =date('Y-m-d');
        $symbol = $request->input('symbol');

        if ($symbol) {
            $this->getInstitutionalOwnership($symbol, $from, $to);
        } else {
            //Log::warning("No symbol provided for institutional ownership batch request.");
        }
    }

    /**
     * Fetches institutional ownership data from the Finnhub API.
     * 
     * @param string $symbol The stock symbol (e.g., "AAPL").
     * @param string $from Start date in YYYY-MM-DD format.
     * @param string $to End date in YYYY-MM-DD format.
     * @return array|null The API response data or null if an error occurs.
     */
    private function fetchInstitutionalOwnershipFromAPI(string $symbol, string $from, string $to): ?array
    {
        $endpoint = '/institutional/ownership';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
            'from'   => $from,
            'to'     => $to,
        ];
        //echo $this->apiBaseUrl . $endpoint.http_build_query($params);
        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . $endpoint, $params);

            if ($response->failed()) {
                //Log::error('API request failed.', [
                //    'status' => $response->status(),
                //    'error'  => $response->json(),
                //]);
                return null;
            }

            $responseData = $response->json();
            $responseData['from'] = $from;
            $responseData['to'] = $to;
            return $responseData;
        } catch (\Exception $e) {
            //Log::error('Exception during API request.', [
            //    'endpoint' => $endpoint,
            //    'message'  => $e->getMessage(),
            //]);
            return null;
        }
    }

    /**
     * Processes and saves the institutional ownership data in the database.
     * 
     * @param array $response The API response data.
     * @param string $symbol The stock symbol associated with the data.
     * @return void
     */
    private function processAndSaveInstitutionalOwnership(array $response, string $symbol): void
    {
        $stockId = Stock::where('symbol', $symbol)->value('id');

        if (!$stockId) {
           /// Log::warning("Stock not found for symbol: {$symbol}");
            return;
        }

        $ownerships = $response['data'][0]['ownership'] ?? [];
        
        if (empty($ownerships)) {
            //Log::warning("No ownership data found for symbol: {$symbol}");
            return;
        }

        foreach ($ownerships as $ownership) {
            $ownership['report_date'] = $response['data'][0]['reportDate'];
            $ownership['from'] = $response['from'];
            $ownership['to'] = $response['to'];

            try {
                StockInstitutionalOwnership::updateStockInstitutionalOwnership($stockId, $ownership);
            } catch (\Exception $e) {
                //Log::error('Error updating institutional ownership', [
                //    'symbol'  => $symbol,
                //    'message' => $e->getMessage(),
                //]);
            }
        }
    }
}
