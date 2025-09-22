<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ProcessStockCandles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stocks:candles';

    /**
     * Description of the command.
     *
     * @var string
     */
    protected $description = 'Process stock candles in batches using concurrent API calls.';

    /**
     * List of API endpoints that will be called concurrently for each stock symbol.
     *
     * @var array
     */
    protected array $endpoints = [
        'https://api.trendseekermax.com/v1/pull_stocks_candlestick_daily_batch'
    ];

    /**
     * Concurrent calls per second (API rate window).
     */
    protected int $rateLimit = 30;

    /**
     * Maximum number of stocks to process on a single cron run.
     */
    protected int $maxPerRun = 2000;

    /**
     * Execute the console command.
     *
     * This method retrieves up to $maxPerRun stock symbols, splits them into chunks
     * (size = $rateLimit) and processes each chunk by sending concurrent API requests.
     * Regardless of API success/failure, processed_candles is set to 1 for the symbols
     * in each chunk so they do not block subsequent cron runs.
     *
     * @return int
     */
    public function handle(): int
    {
        // Fetch up to the configured maximum for this run
        $symbols = $this->getStockSymbols($this->maxPerRun);

        // Chunk into groups to respect rate limit
        $chunks = array_chunk($symbols, $this->rateLimit);

        foreach ($chunks as $chunk) {

            // Build request list (one request per endpoint per symbol)
            $requests = [];
            foreach ($chunk as $symbol) {
                foreach ($this->endpoints as $endpoint) {
                    $requests[] = [
                        'symbol' => $symbol,
                        'endpoint' => $endpoint,
                    ];
                }
            }

            // Fire concurrent requests. Failures are ignored.
            try {
                Http::pool(fn ($pool) =>
                    collect($requests)->map(fn ($req) =>
                        $pool->retry(3, 2000)->get($req['endpoint'], ['symbol' => $req['symbol']])
                    )->toArray()
                );
            } catch (\Throwable $e) {
                // Intentionally ignore network/response errors to avoid blocking processing.
            }

            // Respect rate limit window: wait 1 second before the next batch
            usleep(1000000);
        }

        $this->info(sprintf('Processed %d symbols (max per run: %d).', count($symbols), $this->maxPerRun));

        return 0;
    }

    /**
     * Retrieve an array of stock symbols up to the provided limit.
     *
     * @param int $limit
     * @return array
     */
    protected function getStockSymbols(int $limit = 2000): array
    {
        // NOTE: removed filtering by processed_candles / notpriority so this will
        // return the first $limit symbols ordered by id regardless of flags.
        return DB::table('stocks_by_market_cap')
            ->orderBy('id', 'asc')
            ->take($limit)
            ->pluck('symbol')
            ->toArray();
    }
}
