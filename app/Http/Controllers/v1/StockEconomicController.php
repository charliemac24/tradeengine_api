<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\v1\StockEconomicCalendar;

class StockEconomicController extends Controller
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
     * Get stock economic calendar for all stock symbols.
     *
     * @return array|null The economic calendar data or null on failure.
     */
    public function getStockEconomicCalendar(): ?array
    {
        $response = $this->fetchStockEconomicCalendarFromAPI();

        if ($response) {
            $this->processAndSaveStockEconomicCalendar($response);
            return $response;
        }

        //Log::warning("Failed to fetch stock economic calendar.");
        return null;
    }

    /**
     * Handle batch request for stock economic calendar.
     *
     * @param Request $request
     * @return void
     */
    public function getStockEconomicCalendarBatch(Request $request): void
    {
        $this->getStockEconomicCalendar();
    }

    /**
     * Fetch stock economic calendar data from the Finnhub API.
     *
     * @return array|null The API response data or null if an error occurred.
     */
    private function fetchStockEconomicCalendarFromAPI(): ?array
    {      
        $endpoint = '/calendar/economic';
        $params = ['token' => $this->apiKey];
        
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
           // Log::error('An exception occurred during API request.', [
           //     'endpoint' => $endpoint,
           //     'message' => $e->getMessage(),
           // ]);
            return null;
        }
    }

    /**
     * Process and save stock economic calendar data.
     *
     * @param array $response The API response data.
     * @return void
     */
    private function processAndSaveStockEconomicCalendar(array $response): void
    {
        if (!isset($response['economicCalendar']) || !is_array($response['economicCalendar'])) {
            //Log::warning('Invalid economic calendar data received.');
            return;
        }

        foreach ($response['economicCalendar'] as $economic) {
            if (!empty($economic['time']) && ($economic['country'] ?? '') === 'US') {
                StockEconomicCalendar::updateStockEconomicCalendar($economic);
            }
        }
    }
}
