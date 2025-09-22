<?php


namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ProcessStockNews extends Command
{
    protected $signature = 'stocks:company_news';
    protected $description = 'Process company news for stocks (single endpoint, no DB status updates).';

    protected string $endpoint = 'https://api.trendseekermax.com/v1/pull_stock_company_news_batch';
    protected int $rateLimit = 30;   // requests per second
    protected int $maxPerRun = 2000; // strict upper cap of symbols per run

    public function handle(): int
    {
        $symbols = $this->getStockSymbols($this->maxPerRun);


        $chunks = array_chunk($symbols, $this->rateLimit);

        foreach ($chunks as $chunk) {
            // Fire concurrent requests; ignore failures
            try {
                Http::pool(fn ($pool) =>
                    collect($chunk)->map(fn ($symbol) =>
                        $pool->retry(2, 1000)
                             ->timeout(10)
                             ->get($this->endpoint, ['symbol' => $symbol])
                    )->toArray()
                );
            } catch (\Throwable $e) {
                // ignore HTTP errors
            }

            usleep(1_000_000); // 1 second pause to respect rate limit
        }

        return 0;
    }

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