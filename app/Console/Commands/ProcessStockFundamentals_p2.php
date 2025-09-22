<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ProcessStockFundamentals_p2 extends Command
{
    /**
     * The name and signature of the console command.
     * This command can be scheduled via Laravel's scheduler or run manually via CLI.
     *
     * @var string
     */
    protected $signature = 'stocks:fundamentals_p2';

    /**
     * Description of the command.
     *
     * @var string
     */
    protected $description = 'Process stock fundamentals part 2 (no DB status updates).';

    /**
     * List of API endpoints that will be called concurrently for each stock symbol.
     *
     * @var array
     */
    protected array $endpoints = [
        // Fundamentals part 2
        'https://api.trendseekermax.com/v1/pull_stock_social_sentiments_batch',
        'https://api.trendseekermax.com/v1/pull_stock_upgrade_downgrade_batch',
        'https://api.trendseekermax.com/v1/pull_stock_institutional_ownership_batch',
        'https://api.trendseekermax.com/v1/pull_stock_institutional_portfolio_batch',
        'https://api.trendseekermax.com/v1/pull_stock_recommendation_batch',
        // Not sure what this is 'https://api.trendseekermax.com/v1/pull_stock_earnings_quality_score_batch',
        'https://api.trendseekermax.com/v1/pull_stock_dividend_batch',
        'https://api.trendseekermax.com/v1/pull_stock_earnings_quality_quarterly_batch',
        //'https://api.trendseekermax.com/v1/update_company_name_batch',
        'https://api.trendseekermax.com/v1/get_company_peers_batch',
        'https://api.trendseekermax.com/v1/log-stock-events',
    ];

    protected int $rateLimit = 30;   // requests per second
    protected int $maxPerRun = 2000; // strict upper cap

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
        $symbols = $this->getStockSymbols($this->maxPerRun);


        $tasks = [];
        foreach ($symbols as $symbol) {
            foreach ($this->endpoints as $endpoint) {
                $tasks[] = ['endpoint' => $endpoint, 'symbol' => $symbol];
            }
        }

        $batches = array_chunk($tasks, $this->rateLimit);

        foreach ($batches as $batch) {
            try {
                Http::pool(fn($pool) =>
                    collect($batch)->map(fn($t) =>
                        $pool->retry(3, 2000)
                             ->timeout(10)
                             ->get($t['endpoint'], ['symbol' => $t['symbol']])
                    )->toArray()
                );
            } catch (\Throwable $e) {
                // ignore failures
            }
            usleep(1000000); // 1 second
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
    protected function getStockSymbols(int $limit = 2000): array
    {
        // Removed WHERE filters; only ordering + limit
        return DB::table('stocks_by_market_cap')
            ->orderBy('id', 'asc')
            ->take($limit)
            ->pluck('symbol')
            ->toArray();
    }
}
