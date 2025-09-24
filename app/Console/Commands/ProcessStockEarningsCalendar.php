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

    /**
     * Concurrent calls per second (API rate window).
     */
    protected int $rateLimit = 30;

    /**
     * Maximum number of stocks to process on a single cron run.
     */
    protected int $maxPerRun = 2000;

    /**
     * Table name for stock commands.
     */
    protected string $table = 'stocks_commands';

    /**
     * Execute the console command.
     *
     * This method retrieves up to $maxPerRun stock symbols, splits them into chunks
     * (size = $rateLimit) and processes each chunk by sending concurrent API requests.
     * Regardless of API success/failure, processed_earnings_calendar is set to 1 for the symbols
     * in each chunk so they do not block subsequent cron runs.
     *
     * @return int
     */
    public function handle(): int
    {
        // Start of cycle: if no pending (earnings_calendar=0), reset all to 0 so a new full pass begins.
        if (!DB::table($this->table)->where('earnings_calendar', 0)->exists()) {
            DB::table($this->table)->update(['earnings_calendar' => 0]);
            $this->info('No pending symbols. Reset all earnings_calendar to 0 (new cycle started).');
        }

        // Fetch up to the configured maximum for this run
        $symbols = $this->getStockSymbols($this->maxPerRun);

        if (empty($symbols)) {
            $this->info('No symbols to process after reset. Exiting.');
            return 0;
        }

        // Chunk into groups to respect rate limit
        $chunks = array_chunk($symbols, $this->rateLimit);
        $totalSuccess = 0;

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

            $responses = [];
            // Fire concurrent requests. Failures are ignored.
            try {
                $responses = Http::pool(fn ($pool) =>
                    collect($requests)->map(fn ($req) =>
                        $pool->retry(3, 2000)->get($req['endpoint'], ['symbol' => $req['symbol']])
                    )->toArray()
                );
            } catch (\Throwable $e) {
                // Intentionally ignore network/response errors to avoid blocking processing.
            }

            // Determine successful symbols (any 200+ success for that symbol counts)
            $successfulSymbols = [];
            foreach ($responses as $idx => $response) {
                if (isset($requests[$idx]) && $response && method_exists($response, 'successful') && $response->successful()) {
                    $successfulSymbols[] = $requests[$idx]['symbol'];
                }
            }
            $successfulSymbols = array_values(array_unique($successfulSymbols));

            if ($successfulSymbols) {
                DB::table($this->table)
                    ->whereIn('symbol', $successfulSymbols)
                    ->update(['earnings_calendar' => 1]);
                $totalSuccess += count($successfulSymbols);
            }

            // Respect rate limit window: wait 1 second before the next batch
            usleep(1000000);
        }

        // Per spec: force set all to 1 at end to avoid blocking even if some failed.
        DB::table($this->table)->update(['earnings_calendar' => 1]);

        $this->info(sprintf(
            'Processed %d symbols (successfully flagged this run: %d, max per run: %d). All earnings_calendar now set to 1.',
            count($symbols),
            $totalSuccess,
            $this->maxPerRun
        ));

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
        return DB::table($this->table)
            ->where('earnings_calendar', 0)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->pluck('symbol')
            ->toArray();
    }
}