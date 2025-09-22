<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\StockSectorMetrics;

class StockSectorMetricsController extends Controller
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

    public function getStockSectorMetics()
    {

        $response = $this->fetchStockSectorMeticsFromAPI();
        if ($response) {
            $this->processAndSaveStockSectorMetrics($response);
            //return $response;
        } else {
            //Log::warning("Failed to fetch stock recommendation trends for symbol: $symbol");
            return null;
        }
    }

    private function fetchStockSectorMeticsFromAPI(): ?array
    {
        $endpoint = '/sector/metrics';
        $params = [
            'region' => 'NA',
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

    public function processAndSaveStockSectorMetrics($response)
    {
        $data_array = $response['data'];
        foreach($data_array as $data){
            StockSectorMetrics::updateStockSectorMetrics($data);
        }
    }
}