<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessStocksIndicatorsBatch2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stocks:indicators_batch2';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process stock symbols in batches using concurrent API calls (Indicators batch 2).';

    /**
     * List of endpoints for fetching stock indicators.
     *
     * @var array
     */
    protected array $endpoints = [
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/aroonDown',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/cci',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/lowerB',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/price',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/bullish',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/bearish',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/ema100',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/adx',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/upperB',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/middleB',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/slowk',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/slowd',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/sar',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/obv',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/plusdi',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/minusdi'
    ];

    protected int $rateLimit = 30; // 30 requests/sec per endpoint

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // Retrieve stock symbols from the database.
        $symbols = $this->getStockSymbols();

        if (empty($symbols)) {
            DB::table('stocks_by_market_cap')->update(['processed_indicator_non_hist_p2' => 0]);
            $this->info('All stocks processed. Resetting processed_indicator_non_hist to 0 for next cycle.');
            return 0;
        }

        // For each endpoint, process all stocks in batches of 30
        foreach ($this->endpoints as $endpoint) {
            $chunks = array_chunk($symbols, $this->rateLimit);

             // Mark only this chunk as processed
                DB::table('stocks_by_market_cap')
                    ->whereIn('symbol', $chunk)
                    ->update(['processed_indicator_non_hist_p2' => 1]);


            foreach ($chunks as $chunk) {
                // Use Http::pool for concurrent requests
                $responses = Http::pool(fn ($pool) =>
                    collect($chunk)->map(fn ($symbol) =>
                        $pool->retry(3, 2000)->get($endpoint, ['symbol' => $symbol])
                    )->toArray()
                );

                // Optionally log failed requests
                foreach ($chunk as $i => $symbol) {
                    if (!$responses[$i]->successful()) {
                        Log::error("Failed to process $symbol at $endpoint: " . $responses[$i]->body());
                    }
                }

                // Mark only this chunk as processed
                DB::table('stocks_by_market_cap')
                    ->whereIn('symbol', $chunk)
                    ->update(['processed_indicator_non_hist_p2' => 1]);

                // Wait 1 second before next batch to respect rate limit
                usleep(1000000);
            }
        }

        return 0;
    }

    /**
     * Retrieve all stock symbols from the database.
     *
     * @return array
     */
    protected function getStockSymbols(): array
    {
        return DB::table('stocks_by_market_cap')
            ->where('processed_indicator_non_hist_p2', 0)
            ->where('notpriority', 0)
            ->orderBy('id', 'asc')
            ->pluck('symbol')
            ->toArray();
    }
}
