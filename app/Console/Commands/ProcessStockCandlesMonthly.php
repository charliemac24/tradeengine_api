<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Helpers\ExecutionTimer;
use Illuminate\Support\Facades\Log;

class ProcessStockCandlesMonthly extends Command
{
    /**
     * The name and signature of the console command.
     * This command can be scheduled via Laravel's scheduler or run manually via CLI.
     *
     * @var string
     */
    protected $signature = 'stocks:candles_monthly';

    /**
     * Description of the command.
     *
     * @var string
     */
    protected $description = 'Process stock monthly candles in batches using concurrent API calls.';

    /**
     * List of API endpoints that will be called concurrently for each stock symbol.
     *
     * @var array
     */
    protected array $endpoints = [
        //'https://api.trendseekermax.com/v1/pull_stocks_candlestick_daily_batch',
        //'https://api.trendseekermax.com/v1/pull_stocks_candlestick_weekly_batch',
        'https://api.trendseekermax.com/v1/pull_stocks_candlestick_monthly_batch'
    ];

    /**
     * Execute the console command.
     *
     * This method retrieves stock symbols, splits them into chunks,
     * and processes each chunk by sending concurrent API requests.
     *
     * @return int
     */
    public function handle(): int
    {
        // Retrieve stock symbols from the database.
        $symbols = $this->getStockSymbols();

        if (!empty($symbols)) {
            // Define chunk size (e.g., process 50 symbols per batch).
            $chunkSize = 50;
            $chunks = array_chunk($symbols, $chunkSize);

            // Process each chunk sequentially.
            foreach ($chunks as $index => $chunk) {
                // Process each stock symbol in the current chunk.
                foreach ($chunk as $symbol) {
                    $this->processSymbol($symbol);
                }

                // Pause between chunks to control API request rate.
                if ($index + 1 < count($chunks)) {
                    sleep(60);
                }
            }
        } else {
            // If no symbols left, reset all processed_candles_monthly to 0
            DB::table('stocks_by_market_cap')->update(['processed_candles_monthly' => 0]);
        }

        return 0;
    }

    /**
     * Retrieve an array of stock symbols.
     *
     * The stock symbols are fetched from the database and returned as an array.
     *
     * @return array
     */
    protected function getStockSymbols(): array
    {
        // Directly fetch symbols from stocks_by_market_cap where processed_candles_monthly = 0 AND notpriority = 0
        return DB::table('stocks_by_market_cap')
            ->where('processed_candles_monthly', 0)
            ->where('notpriority', 0)
            ->pluck('symbol')
            ->toArray();
    }

    /**
     * Process an individual stock symbol by making concurrent API calls.
     *
     * @param string $symbol The stock symbol to process.
     */
    protected function processSymbol(string $symbol): void
    {
        foreach ($this->endpoints as $endpoint) {
            try {
                // Retry up to 3 times, waiting 2 seconds between attempts
                Http::retry(3, 2000)->get($endpoint, ['symbol' => $symbol]);
            } catch (\Exception $e) {
                // Optionally log the failure
                Log::error("Failed to call $endpoint for $symbol: " . $e->getMessage());
            }
        }
        // After processing, set processed_candles_monthly = 1 for this symbol
        DB::table('stocks_by_market_cap')
            ->where('symbol', $symbol)
            ->update(['processed_candles_monthly' => 1]);
    }

    /**
     * Execute static API calls that do not require symbols.
     *
     * This is used for global API calls like fetching economic and earnings calendar data.
     *
     * @param array $urls The list of API URLs to call.
     */
    protected function runStaticApiCalls(array $urls): void
    {
        foreach ($urls as $url) {
            try {
                Http::get($url);
            } catch (\Exception $e) {
                // Do nothing on failure
            }
        }
    }
}
