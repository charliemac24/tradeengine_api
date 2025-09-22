<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ProcessStockEarningsCalendar extends Command
{
    /**
     * The name and signature of the console command.
     * This command can be scheduled via Laravel's scheduler or run manually via CLI.
     *
     * @var string
     */
    protected $signature = 'stocks:earnings_calendar';

    /**
     * Description of the command.
     *
     * @var string
     */
    protected $description = 'Fetch earnings calendar data for up to a fixed number of symbols (API only, no DB status updates).';

    /**
     * List of API endpoints that will be called concurrently for each stock symbol.
     *
     * @var array
     */
    protected array $endpoints = [
        'https://api.trendseekermax.com/v1/earnings-calendar',
    ];

    protected int $chunkSize = 30; // requests per second
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
        $symbols = $this->getStockSymbols($this->maxPerRun);

        $chunks = array_chunk($symbols, $this->chunkSize);

        foreach ($chunks as $chunk) {
            try {
                Http::pool(fn($pool) =>
                    collect($chunk)->map(fn($symbol) =>
                        $pool->retry(2, 1000)
                             ->timeout(10)
                             ->get($this->endpoints[0], ['symbol' => $symbol])
                    )->toArray()
                );
            } catch (\Throwable $e) {
                // ignore HTTP errors
            }

            usleep(1_000_000); // pause 1 second to respect rate limit
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
        // Removed WHERE filters; only ordering + strict limit via take()
        return DB::table('stocks_by_market_cap')
            ->orderBy('id', 'asc')
            ->take($limit)
            ->pluck('symbol')
            ->toArray();
    }
}