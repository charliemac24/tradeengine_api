<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ProcessStocksTradingScore extends Command
{
    /**
     * The name and signature of the console command.
     * This command can be scheduled via Laravel's scheduler or run manually via CLI.
     *
     * @var string
     */
    protected $signature = 'stocks:trading_score';

    /**
     * Description of the command.
     *
     * @var string
     */
    protected $description = 'Call trading score endpoint for a limited set of symbols (API only, no DB status updates).';

    /**
     * List of API endpoints that will be called concurrently for each stock symbol.
     *
     * @var array
     */
    protected array $endpoints = [
        'https://api.trendseekermax.com/v1/stocks_scoring',
    ];

    /**
     * Rate limit for API requests.
     *
     * @var int
     */
    protected int $rateLimit = 30;    // requests per second
    protected int $maxPerRun = 2000;  // strict upper cap

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
        $symbols = $this->getStockSymbols($this->maxPerRun);

        $chunks = array_chunk($symbols, $this->rateLimit);

        foreach ($chunks as $chunk) {
            try {
                Http::pool(fn($pool) =>
                    collect($chunk)->map(fn($symbol) =>
                        $pool->retry(3, 2000)
                             ->timeout(10)
                             ->get($this->endpoints[0], ['symbol' => $symbol])
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
     * Retrieve an array of stock symbols.
     *
     * The stock symbols are fetched from the database and returned as an array.
     *
     * @return array
     */
    protected function getStockSymbols(int $limit = 2000): array
    {
        // Removed WHERE filters & update logic; just ordered limited selection
        return DB::table('stocks_by_market_cap')
            ->orderBy('id', 'asc')
            ->take($limit)
            ->pluck('symbol')
            ->toArray();
    }
}