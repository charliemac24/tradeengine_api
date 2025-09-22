<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\v1\Stock;
use App\Models\v1\StockEarningsCalendar;

class StockEarningsCalendarController extends Controller
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
     * StockEarningsCalendarController constructor.
     * Initializes API credentials from the configuration.
     */
    public function __construct()
    {
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey = config('services.finnhub.key');
    }

    /**
     * Fetch and process stock earnings calendar data.
     *
     * @return array|null The earnings calendar data or null if an error occurred.
     */
    public function getStockEarningsCalendar($request)
    {
        $response = $this->fetchStockEarningsCalendarFromAPI($request);

        if ($response) {
            $this->processAndSaveStockEarningsCalendar($response);
            return $response;
        }

        return null;
    }

    /**
     * Fetch stock earnings calendar for a batch of stock symbols.
     *
     * @param Request $request HTTP request instance containing stock symbols.
     * @return void
     */
    public function getStockEarningsCalendarBatch(Request $request)
    {
        $this->getStockEarningsCalendar($request);
    }

    /**
     * Fetch stock earnings calendar data from the Finnhub API.
     *
     * @return array|null The API response data or null if an error occurred.
     */
    private function fetchStockEarningsCalendarFromAPI($request): ?array
    {
        $from = date('Y-m-d', strtotime('-2 week'));
        $to = date('Y-m-d', strtotime('14 days'));

        $endpoint = '/calendar/earnings';
        $params = [
            'token' => $this->apiKey,
            'from' => $from,
            'to' => $to,
            'symbol' => $request->input('symbol', ''),
        ];
        try {
            echo $this->apiBaseUrl . $endpoint . '?' . http_build_query($params) . "\n"; // Debugging line
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
     * Process and save the stock earnings calendar data.
     *
     * @param array $response The API response data.
     * @return void
     */
    public function processAndSaveStockEarningsCalendar(array $response)
    {
        $earnings = $response['earningsCalendar'] ?? [];

        foreach ($earnings as $earning) {
            if (!empty($earning['date'])) {
                $stockId = Stock::where('symbol', $earning['symbol'])->value('id');
                if ($stockId) {
                    StockEarningsCalendar::updateStockEarningsCalendar($stockId, $earning);
                }
            }
        }
    }
}