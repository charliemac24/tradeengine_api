<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ProcessStockHistoricalIndicators extends Command
{
    protected $signature = 'stocks:historical-indicators';
    protected $description = 'Fetch historical indicators for a batch of symbols (no DB status updates).';

    // Call each endpoint per symbol (adjust as needed)
    protected array $endpoints = [
        'https://api.trendseekermax.com/v1/pull_stock_historical_indicators_batch'
    ];

    protected int $rateLimit = 30;   // requests per second (global across endpoints)
    protected int $maxPerRun = 2000; // strict upper cap

    public function handle(): int
    {
        $symbols = $this->getStockSymbols($this->maxPerRun);


        // Build tasks (endpoint + symbol)
        $tasks = [];
        foreach ($symbols as $symbol) {
            foreach ($this->endpoints as $endpoint) {
                $tasks[] = ['endpoint' => $endpoint, 'symbol' => $symbol];
            }
        }

        // Chunk tasks by rate limit
        $batches = array_chunk($tasks, $this->rateLimit);

        foreach ($batches as $batch) {
            try {
                Http::pool(fn ($pool) =>
                    collect($batch)->map(fn ($t) =>
                        $pool->retry(3, 2000)
                             ->timeout(12)
                             ->get($t['endpoint'], ['symbol' => $t['symbol']])
                    )->toArray()
                );
            } catch (\Throwable $e) {
                // ignore network failures
            }

            // one second pause to respect rate limit
            usleep(1_000_000);
        }

        return 0;
    }

    protected function getStockSymbols(int $limit = 2000): array
    {
        // Removed WHERE filters & processed flags; just take first N symbols
        return DB::table('stocks_by_market_cap')
            ->orderBy('id', 'asc')
            ->take($limit)
            ->pluck('symbol')
            ->toArray();
    }
}
