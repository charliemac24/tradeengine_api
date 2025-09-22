<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\v1\StockInstitutionalPortfolio;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstitutionalPortfolioController extends Controller
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
     * Retrieves and saves institutional portfolio data for all CIK numbers.
     *
     * Fetches data from the Finnhub API and stores it in the database. Logs warnings
     * for any CIK numbers that fail to retrieve data.
     *
     * @return void
     */
    public function getInstitutionalPortfolioBatch(): void
    {
        foreach (StockInstitutionalPortfolio::getCIKNumbers() as $cik) {
            $response = $this->fetchInstitutionalPortfolioFromAPI($cik);

            if ($response) {
                $this->processAndSaveInstitutionalPortfolio($response);
            } else {
                //Log::warning("Failed to fetch institutional portfolio for CIK: $cik");
            }
        }
    }

    /**
     * Fetches institutional portfolio data from the Finnhub API for a given CIK number.
     *
     * @param string $cik The Central Index Key (CIK) for the company.
     *
     * @return array|null The API response data, or null if an error occurred.
     */
    private function fetchInstitutionalPortfolioFromAPI(string $cik): ?array
    {
        $from = date('Y-m-d', strtotime('-5 days'));
        $to =date('Y-m-d');
        $params = [
            'cik' => $cik,
            'token' => $this->apiKey,
            'from' => $from,
            'to' => $to,
        ];

        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . '/institutional/portfolio', $params);

            if ($response->failed()) {
               // Log::error('API request failed.', [
                //    'status' => $response->status(),
                //    'error' => $response->json(),
                //    'cik' => $cik, // Log the CIK for debugging.
               // ]);
                return null;
            }

            $data = $response->json();
            $data['from'] = $from;
            $data['to'] = $to;

            return $data;

        } catch (\Exception $e) {
            //Log::error('An exception occurred during API request.', [
             //   'endpoint' => '/institutional/portfolio',
             //   'message' => $e->getMessage(),
             //   'trace' => $e->getTraceAsString(),
             //   'cik' => $cik, // Log the CIK for debugging.
            //]);
            return null;
        }
    }

    /**
     * Processes and saves the institutional portfolio data retrieved from the API.
     *
     * @param array $response The API response containing the portfolio data.
     *
     * @return void
     */
    public function processAndSaveInstitutionalPortfolio(array $response): void
    {
        if (empty($response['data'])) {
            return;
        }

        $cik = $response['cik'];
        $portfolios = $response['data'][0]['portfolio'];
        $from = $response['from'];
        $to = $response['to'];

        if (!$cik) {
            //Log::warning("CIK is missing in the response.");
            return;
        }

        foreach ($portfolios as $portfolio) {
            $portfolio['report_date'] = $response['data'][0]['reportDate'];
            $portfolio['from'] = $from;
            $portfolio['to'] = $to;
            StockInstitutionalPortfolio::updateStockInstitutionalPortfolio(
                $cik,
                $portfolio
            );
        }
    }
}