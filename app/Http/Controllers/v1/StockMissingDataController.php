<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\v1\Stock;

/**
 * Controller to trigger and monitor processing of missing stock data.
 *
 * Routes (examples):
 *  GET  /stock-missing           -> index()
 *  POST /stock-missing/process  -> process(Request)
 */
class StockMissingDataController extends Controller
{
    /**
     * Simple index that explains available endpoints.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'endpoints' => [
                'POST /stock-missing/process' => [
                    'params' => ['file' => 'optional path to include', 'class' => 'optional FQCN to instantiate', 'queue' => 'optional, boolean (not implemented)'],
                ],
            ],
        ]);
    }

    /**
     * Trigger processing of missing stock data by calling the Artisan command.
     *
     * Accepts optional JSON/form fields:
     *  - file: path to a PHP file to include before running
     *  - class: class name to instantiate
     *  - queue: (boolean) request to queue the job (currently not implemented, runs synchronously)
     */
    public function process(Request $request): JsonResponse
    {
        $file = $request->input('file');
        $class = $request->input('class');
        $queue = filter_var($request->input('queue', false), FILTER_VALIDATE_BOOLEAN);

        if ($queue) {
            // We intentionally keep this simple: queueing infrastructure / job class
            // is not created here. Inform client and run synchronously instead.
            Log::info('StockMissingDataController: queue requested but not implemented; running synchronously.');
        }

        $params = [];
        if ($file) {
            $params['--file'] = $file;
        }
        if ($class) {
            $params['--class'] = $class;
        }

        try {
            $exitCode = Artisan::call('stock:process-missing', $params);
            $output = Artisan::output();

            return response()->json([
                'ok' => $exitCode === 0,
                'exit_code' => $exitCode,
                'output' => $output,
            ], $exitCode === 0 ? 200 : 500);
        } catch (\Throwable $e) {
            Log::error('Error running stock:process-missing: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Backfill missing daily candles for a date range (defaults to Feb 1 2025 - Mar 31 2025).
     *
     * This will, for each active stock, determine which daily timestamps in the
     * requested range are missing from `stock_candle_daily` and will call the
     * Finnhub candles API to retrieve data and insert missing rows.
     *
     * Request params (optional):
     *  - from: ISO date (YYYY-MM-DD) or epoch (int) – default 2025-02-01
     *  - to:   ISO date (YYYY-MM-DD) or epoch (int) – default 2025-03-31
     *  - token: override FINNHUB token (recommended to set via env instead)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function fillMissingDailyData(Request $request): JsonResponse
    {
        // Parse range
        $defaultFrom = Carbon::create(2025, 2, 1)->startOfDay();
        $defaultTo = Carbon::create(2025, 3, 31)->endOfDay();

        $fromInput = $request->input('from');
        $toInput = $request->input('to');

        $from = $defaultFrom;
        $to = $defaultTo;

        if ($fromInput) {
            $from = is_numeric($fromInput) ? Carbon::createFromTimestamp($fromInput) : Carbon::parse($fromInput);
        }
        if ($toInput) {
            $to = is_numeric($toInput) ? Carbon::createFromTimestamp($toInput) : Carbon::parse($toInput);
        }

        $token = config('services.finnhub.key');
        if (empty($token)) {
            return response()->json(['ok' => false, 'error' => 'Finnhub token not provided (env FINNHUB_TOKEN or token param)'], 400);
        }

        $summary = [
            'stocks' => 0,
            'stocks_processed' => 0,
            'rows_inserted' => 0,
            'errors' => [],
        ];

        // Build expected list of daily dates (Y-m-d) between from and to
        $expectedDates = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $expectedDates[] = $cursor->toDateString();
            $cursor->addDay();
        }
        
        // If all priority stocks have been marked processed, reset them to start another cycle
        $priorityTotal = DB::table('stocks_by_market_cap')->where('notpriority', 0)->count();
        $priorityUnprocessed = DB::table('stocks_by_market_cap')
            ->where('notpriority', 0)
            ->where('processed_missing_price_daily', 0)
            ->count();
        if ($priorityTotal > 0 && $priorityUnprocessed === 0) {
            DB::table('stocks_by_market_cap')->where('notpriority', 0)->update(['processed_missing_price_daily' => 0]);
            Log::info('Reset processed_missing_price_daily for all priority stocks to 0');
            $summary['resets'][] = 'priority_flags_reset';
        }

        // Fetch stock symbols from stocks_by_market_cap where missing processed flag.
        // This returns an array of symbols which we then resolve to Stock models
        // to obtain the numeric stock_id used by stock_candle_daily.
        $symbols = DB::table('stocks_by_market_cap')
            ->where('processed_missing_price_daily', 0)
            ->where('notpriority', 0)
            ->pluck('symbol')
            ->toArray();

        $summary['stocks'] = count($symbols);

        foreach ($symbols as $symbol) {
            // Resolve stock id via Stock model
            $stock = Stock::where('symbol', $symbol)->first(['id', 'symbol']);
            if (! $stock) {
                $summary['errors'][] = ['symbol' => $symbol, 'error' => 'Stock model not found'];
                continue;
            }
            $missingDates = [];

            // Query existing ts values for this stock in the period
            $existing = DB::table('stock_candle_daily')
                ->where('stock_id', $stock->id)
                ->whereBetween('ts', [$from->toDateString(), $to->toDateString()])
                ->pluck('ts')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->unique()
                ->values()
                ->toArray();

            // Determine missing dates
            foreach ($expectedDates as $d) {
                if (! in_array($d, $existing, true)) {
                    $missingDates[] = $d;
                }
            }

            if (empty($missingDates)) {
                // Nothing missing for this symbol; mark as processed so it won't be reprocessed
                DB::table('stocks_by_market_cap')->where('symbol', $symbol)->update(['processed_missing_price_daily' => 1]);
                $summary['stocks_processed']++;
                continue;
            }

            $summary['stocks_processed']++;

            // Call Finnhub once for the entire range for this symbol, resolution = D
            $fromTs = $from->timestamp;
            $toTs = $to->timestamp;
            $symbol = $stock->symbol;

            $symbolProcessed = false;
            try {
                $url = 'https://finnhub.io/api/v1/stock/candle';
                $resp = Http::timeout(20)->get($url, [
                    'symbol' => $symbol,
                    'resolution' => 'D',
                    'from' => $fromTs,
                    'to' => $toTs,
                    'token' => $token,
                ]);

                if (! $resp->successful()) {
                    $summary['errors'][] = [ 'symbol' => $symbol, 'error' => 'HTTP ' . $resp->status() ];
                    continue;
                }

                $data = $resp->json();
                if (empty($data) || ($data['s'] ?? null) !== 'ok' || empty($data['t'] ?? [])) {
                    $summary['errors'][] = [ 'symbol' => $symbol, 'error' => 'No data or status != ok', 'body' => $data ];
                    continue;
                }

                // Finnhub returns arrays: t (timestamps), c, h, l, o, v
                $times = $data['t'];
                $closes = $data['c'] ?? [];
                $highs = $data['h'] ?? [];
                $lows = $data['l'] ?? [];
                $opens = $data['o'] ?? [];
                $vols = $data['v'] ?? [];

                $insertRows = [];
                foreach ($times as $i => $ts) {
                    $date = Carbon::createFromTimestamp($ts)->toDateString();
                    if (! in_array($date, $missingDates, true)) {
                        continue; // already present
                    }

                    $insertRows[] = [
                        'stock_id' => $stock->id,
                        'close_price' => $closes[$i] ?? null,
                        'high_price' => $highs[$i] ?? null,
                        'low_price' => $lows[$i] ?? null,
                        'open_price' => $opens[$i] ?? null,
                        'response_status' => $data['s'] ?? 'ok',
                        'ts' => $date,
                        'volume' => $vols[$i] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (! empty($insertRows)) {
                    // Insert in chunks to avoid too large single query
                    $chunks = array_chunk($insertRows, 200);
                    foreach ($chunks as $c) {
                        // Insert only if missing (ignore duplicates). This requires a
                        // unique index on (stock_id, ts) to avoid duplicate-key errors.
                        DB::table('stock_candle_daily')->insertOrIgnore($c);
                        // insertOrIgnore does not return number inserted reliably across drivers,
                        // so we attempt to estimate by counting what we tried to insert.
                        $summary['rows_inserted'] += count($c);
                    }
                    $symbolProcessed = true;
                } else {
                    // No rows to insert but we fetched data — consider processed
                    $symbolProcessed = true;
                }

                // Small throttle to avoid rate-limiting
                usleep(200000); // 200ms
            } catch (\Throwable $e) {
                $summary['errors'][] = [ 'symbol' => $symbol, 'error' => $e->getMessage() ];
                continue;
            }

            // If processing succeeded for this symbol, mark it as processed in stocks_by_market_cap
            if ($symbolProcessed) {
                try {
                    DB::table('stocks_by_market_cap')->where('symbol', $symbol)->update(['processed_missing_price_daily' => 1]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to mark stocks_by_market_cap processed for ' . $symbol . ': ' . $e->getMessage());
                }
            }
        }

        return response()->json(['ok' => true, 'summary' => $summary]);
    }

    /**
     * Backfill missing monthly candles for a date range (defaults to Feb 1 2025 - Mar 31 2025).
     *
     * Same behavior as fillMissingWeeklyData but uses Finnhub resolution 'M' and
     * builds expected dates per-month (month start dates).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function fillMissingMonthlyData(Request $request): JsonResponse
    {
        // Parse range (same defaults)
        $defaultFrom = Carbon::create(2025, 2, 1)->startOfDay();
        $defaultTo = Carbon::create(2025, 3, 31)->endOfDay();

        $fromInput = $request->input('from');
        $toInput = $request->input('to');

        $from = $defaultFrom;
        $to = $defaultTo;

        if ($fromInput) {
            $from = is_numeric($fromInput) ? Carbon::createFromTimestamp($fromInput) : Carbon::parse($fromInput);
        }
        if ($toInput) {
            $to = is_numeric($toInput) ? Carbon::createFromTimestamp($toInput) : Carbon::parse($toInput);
        }

        $token = config('services.finnhub.key');
        if (empty($token)) {
            return response()->json(['ok' => false, 'error' => 'Finnhub token not provided (env FINNHUB_TOKEN or token param)'], 400);
        }

        $summary = [
            'stocks' => 0,
            'stocks_processed' => 0,
            'rows_inserted' => 0,
            'errors' => [],
        ];

        // Build expected list of monthly dates (Y-m-d) between from and to using month starts
        $expectedDates = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $expectedDates[] = $cursor->toDateString();
            $cursor->addMonth();
        }

        // Reset priority cycle if needed (monthly)
        $priorityTotal = DB::table('stocks_by_market_cap')->where('notpriority', 0)->count();
        $priorityUnprocessed = DB::table('stocks_by_market_cap')
            ->where('notpriority', 0)
            ->where('processed_missing_price_monthly', 0)
            ->count();
        if ($priorityTotal > 0 && $priorityUnprocessed === 0) {
            DB::table('stocks_by_market_cap')->where('notpriority', 0)->update(['processed_missing_price_monthly' => 0]);
            Log::info('Reset processed_missing_price_monthly for all priority stocks to 0 (monthly)');
            $summary['resets'][] = 'priority_flags_reset_monthly';
        }

        $symbols = DB::table('stocks_by_market_cap')
            ->where('processed_missing_price_monthly', 0)
            ->where('notpriority', 0)
            ->pluck('symbol')
            ->toArray();
        $summary['stocks'] = count($symbols);

        foreach ($symbols as $symbol) {
            $stock = Stock::where('symbol', $symbol)->first(['id', 'symbol']);
            if (! $stock) {
                $summary['errors'][] = ['symbol' => $symbol, 'error' => 'Stock model not found'];
                continue;
            }

            $missingDates = [];
            $existing = DB::table('stock_candle_monthly')
                ->where('stock_id', $stock->id)
                ->whereBetween('ts', [$from->toDateString(), $to->toDateString()])
                ->pluck('ts')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->unique()
                ->values()
                ->toArray();

            foreach ($expectedDates as $d) {
                if (! in_array($d, $existing, true)) {
                    $missingDates[] = $d;
                }
            }

            if (empty($missingDates)) {
                DB::table('stocks_by_market_cap')->where('symbol', $symbol)->update(['processed_missing_price_monthly' => 1]);
                $summary['stocks_processed']++;
                continue;
            }

            $summary['stocks_processed']++;

            $fromTs = $from->timestamp;
            $toTs = $to->timestamp;
            $symbol = $stock->symbol;

            $symbolProcessed = false;
            try {
                $url = 'https://finnhub.io/api/v1/stock/candle';
                $resp = Http::timeout(20)->get($url, [
                    'symbol' => $symbol,
                    'resolution' => 'M',
                    'from' => $fromTs,
                    'to' => $toTs,
                    'token' => $token,
                ]);

                if (! $resp->successful()) {
                    $summary['errors'][] = [ 'symbol' => $symbol, 'error' => 'HTTP ' . $resp->status() ];
                    continue;
                }

                $data = $resp->json();
                if (empty($data) || ($data['s'] ?? null) !== 'ok' || empty($data['t'] ?? [])) {
                    $summary['errors'][] = [ 'symbol' => $symbol, 'error' => 'No data or status != ok', 'body' => $data ];
                    continue;
                }

                $times = $data['t'];
                $closes = $data['c'] ?? [];
                $highs = $data['h'] ?? [];
                $lows = $data['l'] ?? [];
                $opens = $data['o'] ?? [];
                $vols = $data['v'] ?? [];

                $insertRows = [];
                foreach ($times as $i => $ts) {
                    $date = Carbon::createFromTimestamp($ts)->toDateString();
                    // Normalize monthly timestamp to month start to match expectedDates
                    $monthStart = Carbon::createFromTimestamp($ts)->startOfMonth()->toDateString();
                    if (! in_array($monthStart, $missingDates, true)) {
                        continue; // already present
                    }

                    $insertRows[] = [
                        'stock_id' => $stock->id,
                        'close_price' => $closes[$i] ?? null,
                        'high_price' => $highs[$i] ?? null,
                        'low_price' => $lows[$i] ?? null,
                        'open_price' => $opens[$i] ?? null,
                        'response_status' => $data['s'] ?? 'ok',
                        'ts' => $monthStart,
                        'volume' => $vols[$i] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (! empty($insertRows)) {
                    $chunks = array_chunk($insertRows, 200);
                    foreach ($chunks as $c) {
                        DB::table('stock_candle_monthly')->insertOrIgnore($c);
                        $summary['rows_inserted'] += count($c);
                    }
                    $symbolProcessed = true;
                } else {
                    $symbolProcessed = true;
                }

                usleep(200000);
            } catch (\Throwable $e) {
                $summary['errors'][] = [ 'symbol' => $symbol, 'error' => $e->getMessage() ];
                continue;
            }

            if ($symbolProcessed) {
                try {
                    DB::table('stocks_by_market_cap')->where('symbol', $symbol)->update(['processed_missing_price_monthly' => 1]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to mark stocks_by_market_cap processed for ' . $symbol . ': ' . $e->getMessage());
                }
            }
        }

        return response()->json(['ok' => true, 'summary' => $summary]);
    }

    /**
     * Backfill missing weekly candles for a date range (defaults to Feb 1 2025 - Mar 31 2025).
     *
     * Same behavior as fillMissingDailyData but uses Finnhub resolution 'W' and
     * builds expected dates per-week (week start dates).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function fillMissingWeeklyData(Request $request): JsonResponse
    {
        // Parse range (same defaults as daily)
        $defaultFrom = Carbon::create(2025, 2, 1)->startOfDay();
        $defaultTo = Carbon::create(2025, 3, 31)->endOfDay();

        $fromInput = $request->input('from');
        $toInput = $request->input('to');

        $from = $defaultFrom;
        $to = $defaultTo;

        if ($fromInput) {
            $from = is_numeric($fromInput) ? Carbon::createFromTimestamp($fromInput) : Carbon::parse($fromInput);
        }
        if ($toInput) {
            $to = is_numeric($toInput) ? Carbon::createFromTimestamp($toInput) : Carbon::parse($toInput);
        }

        $token = config('services.finnhub.key');
        if (empty($token)) {
            return response()->json(['ok' => false, 'error' => 'Finnhub token not provided (env FINNHUB_TOKEN or token param)'], 400);
        }

        $summary = [
            'stocks' => 0,
            'stocks_processed' => 0,
            'rows_inserted' => 0,
            'errors' => [],
        ];

        // Build expected list of weekly dates (Y-m-d) between from and to using week starts
        $expectedDates = [];
        $cursor = $from->copy()->startOfWeek();
        while ($cursor->lte($to)) {
            $expectedDates[] = $cursor->toDateString();
            $cursor->addWeek();
        }

        // Reset priority cycle if needed (same logic as daily)
        $priorityTotal = DB::table('stocks_by_market_cap')->where('notpriority', 0)->count();
        $priorityUnprocessed = DB::table('stocks_by_market_cap')
            ->where('notpriority', 0)
            ->where('processed_missing_price_weekly', 0)
            ->count();
        if ($priorityTotal > 0 && $priorityUnprocessed === 0) {
            DB::table('stocks_by_market_cap')->where('notpriority', 0)->update(['processed_missing_price_weekly' => 0]);
            Log::info('Reset processed_missing_price_weekly for all priority stocks to 0 (weekly)');
            $summary['resets'][] = 'priority_flags_reset_weekly';
        }

        $symbols = DB::table('stocks_by_market_cap')
            ->where('processed_missing_price_weekly', 0)
            ->where('notpriority', 0)
            ->pluck('symbol')
            ->toArray();
        $summary['stocks'] = count($symbols);

        foreach ($symbols as $symbol) {
            $stock = Stock::where('symbol', $symbol)->first(['id', 'symbol']);
            if (! $stock) {
                $summary['errors'][] = ['symbol' => $symbol, 'error' => 'Stock model not found'];
                continue;
            }

            $missingDates = [];
            $existing = DB::table('stock_candle_weekly')
                ->where('stock_id', $stock->id)
                ->whereBetween('ts', [$from->toDateString(), $to->toDateString()])
                ->pluck('ts')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->unique()
                ->values()
                ->toArray();

            foreach ($expectedDates as $d) {
                if (! in_array($d, $existing, true)) {
                    $missingDates[] = $d;
                }
            }

            if (empty($missingDates)) {
                DB::table('stocks_by_market_cap')->where('symbol', $symbol)->update(['processed_missing_price_weekly' => 1]);
                $summary['stocks_processed']++;
                continue;
            }

            $summary['stocks_processed']++;

            $fromTs = $from->timestamp;
            $toTs = $to->timestamp;
            $symbol = $stock->symbol;

            $symbolProcessed = false;
            try {
                $url = 'https://finnhub.io/api/v1/stock/candle';
                $resp = Http::timeout(20)->get($url, [
                    'symbol' => $symbol,
                    'resolution' => 'W',
                    'from' => $fromTs,
                    'to' => $toTs,
                    'token' => $token,
                ]);

                if (! $resp->successful()) {
                    $summary['errors'][] = [ 'symbol' => $symbol, 'error' => 'HTTP ' . $resp->status() ];
                    continue;
                }

                $data = $resp->json();
                if (empty($data) || ($data['s'] ?? null) !== 'ok' || empty($data['t'] ?? [])) {
                    $summary['errors'][] = [ 'symbol' => $symbol, 'error' => 'No data or status != ok', 'body' => $data ];
                    continue;
                }

                $times = $data['t'];
                $closes = $data['c'] ?? [];
                $highs = $data['h'] ?? [];
                $lows = $data['l'] ?? [];
                $opens = $data['o'] ?? [];
                $vols = $data['v'] ?? [];

                $insertRows = [];
                foreach ($times as $i => $ts) {
                    $date = Carbon::createFromTimestamp($ts)->toDateString();
                    // Normalize weekly timestamp to week start to match expectedDates
                    $weekStart = Carbon::createFromTimestamp($ts)->startOfWeek()->toDateString();
                    if (! in_array($weekStart, $missingDates, true)) {
                        continue; // already present
                    }

                    $insertRows[] = [
                        'stock_id' => $stock->id,
                        'close_price' => $closes[$i] ?? null,
                        'high_price' => $highs[$i] ?? null,
                        'low_price' => $lows[$i] ?? null,
                        'open_price' => $opens[$i] ?? null,
                        'response_status' => $data['s'] ?? 'ok',
                        'ts' => $weekStart,
                        'volume' => $vols[$i] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (! empty($insertRows)) {
                    $chunks = array_chunk($insertRows, 200);
                    foreach ($chunks as $c) {
                        DB::table('stock_candle_weekly')->insertOrIgnore($c);
                        $summary['rows_inserted'] += count($c);
                    }
                    $symbolProcessed = true;
                } else {
                    $symbolProcessed = true;
                }

                usleep(200000);
            } catch (\Throwable $e) {
                $summary['errors'][] = [ 'symbol' => $symbol, 'error' => $e->getMessage() ];
                continue;
            }

            if ($symbolProcessed) {
                try {
                    DB::table('stocks_by_market_cap')->where('symbol', $symbol)->update(['processed_missing_price_weekly' => 1]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to mark stocks_by_market_cap processed for ' . $symbol . ': ' . $e->getMessage());
                }
            }
        }

        return response()->json(['ok' => true, 'summary' => $summary]);
    }
}
