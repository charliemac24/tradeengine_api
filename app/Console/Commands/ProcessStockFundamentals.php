<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ProcessStockFundamentals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stocks:fundamentals';

    /**
     * Description of the command.
     *
     * @var string
     */
    protected $description = 'Process stock fundamentals in batches using concurrent API calls (no DB status updates).';

    /**
     * Endpoints to call for each symbol.
     *
     * @var array
     */
    protected array $endpoints = [
        'https://api.trendseekermax.com/v1/pull_stocks_basic_financial_metric_batch',
        'https://api.trendseekermax.com/v1/pull_stock_price_metric_batch',
        'https://api.trendseekermax.com/v1/pull_stock_price_target_batch',
        'https://api.trendseekermax.com/v1/pull_stock_insider_transaction_batch',
        'https://api.trendseekermax.com/v1/pull_stock_quote_batch',
        'https://api.trendseekermax.com/v1/pull_stock_news_sentiments_batch',
        'https://api.trendseekermax.com/v1/pull_stock_earnings_calendar_batch',
        'https://api.trendseekermax.com/v1/stock-peers/fetch-and-save',
        'https://api.trendseekermax.com/v1/fetch-insider-sentiment',
    ];

    /**
     * Global allowed requests per second (total across all endpoints).
     */
    protected int $globalRateLimit = 30;

    /**
     * Maximum number of symbols to process per cron run.
     */
    protected int $maxPerRun = 2000;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // Run static API calls first (no symbols)
        $this->runStaticApiCalls([
            'https://api.trendseekermax.com/v1/pull_stock_economic_calendar_batch',
        ]);

        // Grab up to configured symbols for this run
        $symbols = $this->getStockSymbols($this->maxPerRun);

        // Build flattened list of (endpoint, symbol) pairs and process in globalRateLimit-sized batches
        $pairs = [];
        foreach ($symbols as $symbol) {
            foreach ($this->endpoints as $endpoint) {
                $pairs[] = ['endpoint' => $endpoint, 'symbol' => $symbol];
            }
        }

        $batches = array_chunk($pairs, $this->globalRateLimit);

        foreach ($batches as $batch) {
            try {
                Http::pool(fn ($pool) =>
                    collect($batch)->map(fn ($item) =>
                        $pool->retry(3, 2000)
                             ->timeout(10)
                             ->get($item['endpoint'], ['symbol' => $item['symbol']])
                    )->toArray()
                );
            } catch (\Throwable $e) {
                // ignore failures
                usleep(200000);
            }

            // respect rate limit window: one second between batches
            usleep(1000000);
        }

        return 0;
    }

    /**
     * Retrieve symbols up to limit.
     *
     * @param int $limit
     * @return array
     */
    protected function getStockSymbols(int $limit = 2000): array
    {
        // Removed WHERE filters & any processed_* flag usage
        return DB::table('stocks_by_market_cap')
            ->orderBy('id', 'asc')
            ->take($limit)
            ->pluck('symbol')
            ->toArray();
    }

    /**
     * Execute static API calls that do not require symbols.
     *
     * @param array $urls
     * @return void
     */
    protected function runStaticApiCalls(array $urls): void
    {
        foreach ($urls as $url) {
            try {
                Http::timeout(10)->get($url);
            } catch (\Throwable $e) {
                // ignore
            }
            usleep(200000); // slight spacing
        }
    }
}
