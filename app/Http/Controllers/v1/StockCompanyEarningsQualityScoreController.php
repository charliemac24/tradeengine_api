<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\StockCompanyEarningsQualityScore;
use App\Models\v1\Stock;

/**
 * Class StockCompanyEarningsQualityScoreController
 * Handles fetching and storing stock company earnings quality scores.
 */
class StockCompanyEarningsQualityScoreController extends Controller
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
     * StockCompanyEarningsQualityScoreController constructor.
     */
    public function __construct()
    {
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey = config('services.finnhub.key');
    }

    /**
     * Get stock company earnings quality score for a stock symbol.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStockCompanyEarningsQualityScore(Request $request)
    {
        $symbol = strtoupper($request->input('symbol'));

        $response = $this->fetchStockCompanyEarningsQualityScoreFromAPI($symbol);

        if ($response) {
            $this->processAndSaveStockCompanyEarningsQualityScore($response, $symbol);
            return response()->json($response);
        } else {
           // Log::warning("Failed to fetch stock company earnings quality score for symbol: $symbol");
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    /**
     * Fetch stock company earnings quality score from Finnhub API.
     *
     * @param string $symbol The stock symbol.
     * @return array|null The API response data or null if an error occurred.
     */
    private function fetchStockCompanyEarningsQualityScoreFromAPI(string $symbol): ?array
    {
        $endpoint = '/stock/earnings-quality-score';
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
        ];

        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . $endpoint, $params);

            if ($response->failed()) {
                //Log::error('API request failed.', [
                //    'status' => $response->status(),
                //    'error' => $response->json(),
               // ]);
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
     * Process and save the stock company earnings quality score data.
     *
     * @param array $response The API response data.
     * @param string $symbol The stock symbol.
     * @return void
     */
    private function processAndSaveStockCompanyEarningsQualityScore(array $response, string $symbol): void
    {
        $stockId = Stock::where('symbol', $symbol)->value('id');
        $q_scores = $response['data'];
        
        if ($stockId) {
            foreach($q_scores as $score){
                StockCompanyEarningsQualityScore::updateStockCompanyEarningsQualityScore(
                    $stockId,
                    [
                        'cashGenerationCapitalAllocation' => $score['cashGenerationCapitalAllocation'],
                        'growth' => $score['growth'],
                        'letterScore' => $score['letterScore'],
                        'leverage' => $score['leverage'],
                        'period' => $score['period'],
                        'profitability' => $score['profitability'],
                        'score' => $score['score']
                    ]
                );
            }
        } else {
            //Log::warning("Stock not found for symbol: " . $symbol);
        }
    }
}
