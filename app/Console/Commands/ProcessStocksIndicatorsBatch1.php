<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ProcessStocksIndicatorsBatch1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stocks:indicators_batch1';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Call indicator batch 1 endpoints (API only, no DB status updates).';

    /**
     * List of endpoints for fetching stock indicators.
     *
     * @var array
     */
    protected array $endpoints = [
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/ema50',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/ema200',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/ema10',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/sma50',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/sma10',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/sma20',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/sma100',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/sma200',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/macd',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/macdSignal',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/macdHist',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/rsi',
        'https://api.trendseekermax.com/v1/pull_stock_indicators_batch/aroonUp',
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

    protected int $rateLimit = 30;   // requests per second
    protected int $maxPerRun = 2000; // strict cap

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $symbols = $this->getStockSymbols($this->maxPerRun);

        // Build (endpoint, symbol) task list
        $tasks = [];
        foreach ($symbols as $symbol) {
            foreach ($this->endpoints as $endpoint) {
                $tasks[] = ['endpoint' => $endpoint, 'symbol' => $symbol];
            }
        }

        // Chunk by rate limit
        $batches = array_chunk($tasks, $this->rateLimit);

        foreach ($batches as $batch) {
            try {
                Http::pool(fn($pool) =>
                    collect($batch)->map(fn($t) =>
                        $pool->retry(2, 1000)
                             ->timeout(10)
                             ->get($t['endpoint'], ['symbol' => $t['symbol']])
                    )->toArray()
                );
            } catch (\Throwable $e) {
                // ignore failures
            }
            usleep(1_000_000); // 1 second pause
        }

        return 0;
    }

    /**
     * Retrieve all stock symbols from the database.
     *
     * @return array
     */
    protected function getStockSymbols(int $limit = 2000): array
    {
        // Removed WHERE filters & any update logic; just limit + order.
        return DB::table('stocks_by_market_cap')
            ->orderBy('id', 'asc')
            ->take($limit)
            ->pluck('symbol')
            ->toArray();
    }
}
