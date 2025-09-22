<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class StockSplitsController extends Controller
{
    /**
     * GET /cron/fetch-splits
     *
     * Query params:
     * - symbol (required) e.g. AAPL
     * - from (required) yyyy-mm-dd
     * - to   (required) yyyy-mm-dd
     * - token (optional) Finnhub token (falls back to FINNHUB_TOKEN)
     * - key   (optional) cron secret (if FINNHUB_CRON_KEY is set, it must match)
     */
    public function fetchAndStore(Request $request)
    {
        $cronKey = env('STOCKS_BATCH_TOKEN');
        $providedKey = $request->query('key');
        if ($cronKey && $providedKey !== $cronKey) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string',
            'from' => 'required|date',
            'to' => 'required|date',
            'token' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $symbol = strtoupper($request->query('symbol'));
        $from = $request->query('from');
        $to = $request->query('to');
        $token = $request->query('token') ?? env('FINNHUB_API_KEY');

        if (empty($token)) {
            return response()->json(['message' => 'Finnhub token not provided (set FINNHUB_TOKEN or pass token param)'], 500);
        }

        $url = 'https://finnhub.io/api/v1/stock/split';
        try {
            $response = Http::get($url, [
                'symbol' => $symbol,
                'from' => $from,
                'to' => $to,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            Log::error('Finnhub request exception', ['exception' => $e]);
            return response()->json(['message' => 'Request failed', 'error' => $e->getMessage()], 500);
        }

        if (!$response->ok()) {
            Log::error('Finnhub returned non-200', ['status' => $response->status(), 'body' => $response->body()]);
            return response()->json(['message' => 'Finnhub API error', 'status' => $response->status()], 500);
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            return response()->json(['message' => 'Unexpected Finnhub response format'], 500);
        }

        $inserted = 0;
        DB::beginTransaction();
        try {
            foreach ($payload as $item) {
                // expected keys: symbol, date, fromFactor, toFactor
                if (empty($item['symbol']) || empty($item['date']) || !isset($item['fromFactor']) || !isset($item['toFactor'])) {
                    continue;
                }

                $row = [
                    'symbol' => $item['symbol'],
                    'date' => $item['date'],
                    'from_factor' => $item['fromFactor'],
                    'to_factor' => $item['toFactor'],
                    'processed_flag' => 0,
                ];

                $exists = DB::table('stock_splits')
                    ->where('symbol', $row['symbol'])
                    ->where('date', $row['date'])
                    ->exists();

                if ($exists) {
                    DB::table('stock_splits')
                        ->where('symbol', $row['symbol'])
                        ->where('date', $row['date'])
                        ->update($row);
                } else {
                    DB::table('stock_splits')->insert($row);
                    $inserted++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DB error saving stock splits', ['exception' => $e]);
            return response()->json(['message' => 'DB error', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'OK', 'inserted' => $inserted, 'fetched' => count($payload)]);
    }

    /**
     * Adjust historical OHLC and volume prior to a split date.
     *
     * Request/query params:
     * - symbol (required) e.g. AAPL
     * - date (required) split date yyyy-mm-dd
     * - from_factor (required) numeric
     * - to_factor (required) numeric
     * - table (optional) table name containing OHLC, default: daily_ohlc
     * - date_column (optional) default: date
     * - symbol_column (optional) default: symbol
     *
     * Returns JSON: { message, adjusted_rows, split_ratio }
     */
    public function adjustHistoricalForSplit(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'symbol' => 'required|string',
            'date' => 'required|date',
            'from_factor' => 'required|numeric',
            'to_factor' => 'required|numeric',
            'table' => 'sometimes|string',
            'date_column' => 'sometimes|string',
            'symbol_column' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $symbol = $request->input('symbol');
        $splitDate = $request->input('date');
        $fromFactor = (float) $request->input('from_factor');
        $toFactor = (float) $request->input('to_factor');

        if ($fromFactor == 0) {
            return response()->json(['message' => 'from_factor must not be zero'], 400);
        }

        $table = $request->input('table', 'daily_ohlc');
        $dateCol = $request->input('date_column', 'date');
        $symbolCol = $request->input('symbol_column', 'symbol');

        // Basic name validation to avoid SQL injection via identifiers
        $identifierRegex = '/^[A-Za-z0-9_]+$/';
        foreach ([$table, $dateCol, $symbolCol] as $ident) {
            if (!preg_match($identifierRegex, $ident)) {
                return response()->json(['message' => 'Invalid table or column name provided'], 400);
            }
        }

        $splitRatio = $toFactor / $fromFactor;
        if ($splitRatio == 1.0) {
            return response()->json(['message' => 'Split ratio is 1.0; no adjustments needed', 'split_ratio' => $splitRatio]);
        }

        // Columns we will update (change if your schema uses other names)
        $priceCols = ['open', 'high', 'low', 'close'];
        $volumeCol = 'volume';

        // Build SQL safely for identifier names (values are bound)
        $priceSetParts = [];
        foreach ($priceCols as $col) {
            if (!preg_match($identifierRegex, $col)) {
                return response()->json(['message' => 'Invalid price column name in code'], 500);
            }
            $priceSetParts[] = "{$col} = {$col} / ?";
        }
        $priceSetSql = implode(', ', $priceSetParts);
        $volSetSql = "{$volumeCol} = {$volumeCol} * ?";

        $sql = "UPDATE {$table} SET {$priceSetSql}, {$volSetSql} WHERE {$symbolCol} = ? AND {$dateCol} < ?";

        DB::beginTransaction();
        try {
            $bindings = [];
            // one binding per price column for division
            for ($i = 0; $i < count($priceCols); $i++) {
                $bindings[] = $splitRatio;
            }
            // binding for volume multiplier
            $bindings[] = $splitRatio;
            // symbol and date bindings
            $bindings[] = $symbol;
            $bindings[] = $splitDate;

            $affected = DB::update($sql, $bindings);

            DB::commit();

            return response()->json([
                'message' => 'Adjusted historical prices and volume',
                'adjusted_rows' => $affected,
                'split_ratio' => $splitRatio,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Error adjusting historical prices for split', ['exception' => $e, 'symbol' => $symbol, 'date' => $splitDate]);
            return response()->json(['message' => 'DB update failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /v1/stock-splits/adjust-latest-candle
     *
     * Adjust only the latest stock_candle_daily row (by ts) for a split.
     *
     * Query/Request params:
     * - symbol (required) e.g. AAPL
     * - date (required) split date yyyy-mm-dd (we use as upper bound; we update the latest ts < date)
     * - from_factor (required) numeric
     * - to_factor (required) numeric
     *
     * Optional params to override names (defaults match your schema):
     * - candle_table (default: stock_candle_daily)
     * - ts_column (default: ts)
     * - stock_id_column (default: stock_id)
     * - close_column (default: close_price)
     * - volume_column (default: volume)
     * - symbol_table (default: stock_symbols)
     * - symbol_id_column (default: id)
     * - symbol_name_column (default: symbol)
     */
    public function adjustLatestCandleForSplit(Request $request)
    {
        // Retrieve split rows for priority=1 stocks and include stock_symbols.id as stock_id
        $rows = DB::table('stock_splits')
            ->join('stock_symbols', DB::raw("stock_splits.symbol COLLATE utf8mb4_unicode_ci"), '=', DB::raw("stock_symbols.symbol COLLATE utf8mb4_unicode_ci"))
            ->where('stock_symbols.priority', 1)
            ->select('stock_splits.*', 'stock_symbols.id as stock_id')
            ->get();

        $results = [];
        $updatedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $r) {
                $from = isset($r->from_factor) ? (float) $r->from_factor : 0.0;
                $to = isset($r->to_factor) ? (float) $r->to_factor : 0.0;

                if ($from == 0.0) {
                    $results[] = [
                        'split' => $r,
                        'error' => 'from_factor is zero or invalid'
                    ];
                    continue;
                }

                $split_ratio = $to / $from;

                // Determine the date range to fetch candles from Finnhub:
                // - from: the earliest `ts` in `stock_candle_daily` for this stock
                // - to: the day before the split date
                $splitDate = $r->date;
                try {
                    $toDate = Carbon::parse($splitDate)->subDay()->toDateString();
                } catch (\Exception $e) {
                    $results[] = [
                        'split' => $r,
                        'error' => 'Invalid split date',
                    ];
                    continue;
                }

                // Get earliest local candle ts for this stock (use as 'from')
                $firstCandle = DB::table('stock_candle_daily')
                    ->where('stock_id', $r->stock_id)
                    ->orderBy('ts', 'asc')
                    ->select('ts')
                    ->first();

                if ($firstCandle && !empty($firstCandle->ts)) {
                    $fromDate = $firstCandle->ts;
                } else {
                    // If we don't have any local candles, fall back to 1 year before split (reasonable default)
                    $fromDate = Carbon::parse($toDate)->subYear()->toDateString();
                }

                // Convert to UNIX timestamps (seconds) expected by Finnhub
                try {
                    $fromTs = Carbon::parse($fromDate)->startOfDay()->timestamp;
                    $toTs = Carbon::parse($toDate)->endOfDay()->timestamp;
                } catch (\Exception $e) {
                    $results[] = [
                        'split' => $r,
                        'error' => 'Invalid candle date range',
                    ];
                    continue;
                }

                // Call Finnhub /stock/candle endpoint with resolution=1
                $finnhubToken = env('FINNHUB_API_KEY');
                $candlePayload = null;
                try {
                    $fhUrl = 'https://finnhub.io/api/v1/stock/candle';
                    $fhResp = Http::get($fhUrl, [
                        'symbol' => $r->symbol,
                        'resolution' => '1',
                        'from' => $fromTs,
                        'to' => $toTs,
                        'token' => $finnhubToken,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Finnhub candle request exception', ['exception' => $e, 'symbol' => $r->symbol]);
                    $results[] = [
                        'split' => $r,
                        'split_ratio' => $split_ratio,
                        'candle_found' => false,
                        'finnhub_error' => $e->getMessage(),
                    ];
                    continue;
                }

                if (!$fhResp->ok()) {
                    Log::error('Finnhub candle non-200', ['status' => $fhResp->status(), 'body' => $fhResp->body(), 'symbol' => $r->symbol]);
                    $results[] = [
                        'split' => $r,
                        'split_ratio' => $split_ratio,
                        'candle_found' => false,
                        'finnhub_status' => $fhResp->status(),
                    ];
                    continue;
                }

                $candlePayload = $fhResp->json();

                // Transform Finnhub arrays into list of candle objects for readability
                $candles = [];
                if (isset($candlePayload['s']) && $candlePayload['s'] === 'ok') {
                    $times = $candlePayload['t'] ?? [];
                    $opens = $candlePayload['o'] ?? [];
                    $highs = $candlePayload['h'] ?? [];
                    $lows = $candlePayload['l'] ?? [];
                    $closes = $candlePayload['c'] ?? [];
                    $vols = $candlePayload['v'] ?? [];
                    $count = min(count($times), count($opens), count($highs), count($lows), count($closes), count($vols));
                    for ($i = 0; $i < $count; $i++) {
                        try {
                            $dt = Carbon::createFromTimestamp($times[$i])->toDateTimeString();
                        } catch (\Exception $e) {
                            $dt = date('Y-m-d H:i:s', $times[$i]);
                        }
                        $candles[] = [
                            'ts' => $times[$i],
                            'datetime' => $dt,
                            'open' => $opens[$i],
                            'high' => $highs[$i],
                            'low' => $lows[$i],
                            'close' => $closes[$i],
                            'volume' => $vols[$i],
                        ];
                    }
                }

                // Reduce candles to one (the last) per calendar date in US Eastern timezone
                $dailyMap = [];
                foreach ($candles as $c) {
                    // Convert timestamp to America/New_York and use Y-m-d as key
                    try {
                        $dt = Carbon::createFromTimestamp($c['ts'], 'America/New_York');
                        $dateKey = $dt->format('Y-m-d');
                        $formattedDt = $dt->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        $dateKey = date('Y-m-d', $c['ts']);
                        $formattedDt = date('Y-m-d H:i:s', $c['ts']);
                    }

                    // Keep the candle with the greatest ts for the date
                    if (!isset($dailyMap[$dateKey]) || $c['ts'] > $dailyMap[$dateKey]['last_ts']) {
                        // represent the day by its start (00:00:00) in America/New_York
                        try {
                            $dayStart = Carbon::createFromFormat('Y-m-d', $dateKey, 'America/New_York')->startOfDay();
                            $dayStartTs = $dayStart->timestamp;
                            $dayStartFormatted = $dayStart->format('Y-m-d H:i:s');
                        } catch (\Exception $e) {
                            $dayStartTs = strtotime($dateKey . ' 00:00:00');
                            $dayStartFormatted = date('Y-m-d H:i:s', $dayStartTs);
                        }

                        $dailyMap[$dateKey] = [
                            'ts' => $dayStartTs,
                            'datetime' => $dayStartFormatted,
                            // keep original last-ts for reference and comparison
                            'last_ts' => $c['ts'],
                            'last_datetime' => $c['datetime'],
                            'open' => $c['open'],
                            'high' => $c['high'],
                            'low' => $c['low'],
                            'close' => $c['close'],
                            'volume' => $c['volume'],
                        ];
                    }
                }

                // Re-index and sort by date ascending
                ksort($dailyMap);
                $dailyCandles = array_values($dailyMap);

                // Update local `stock_candle_daily` rows for this stock where ts == last_datetime and stock_id matches
                $affected = 0;
                foreach ($dailyMap as $dateKey => $entry) {
                    $lastDatetime = $entry['last_datetime'] ?? null;
                    if (empty($lastDatetime)) {
                        Log::info('No last_datetime available for candle entry, skipping update', ['symbol' => $r->symbol, 'stock_id' => $r->stock_id, 'dateKey' => $dateKey]);
                        continue;
                    }

                    try {
                        $u = DB::table('stock_candle_daily')
                            ->where('stock_id', $r->stock_id)
                            ->where('ts', $entry['datetime'])
                            ->update([
                                'close_price' => $entry['close'],
                                'volume' => $entry['volume'],
                            ]);

                        if ($u) {
                            $affected += $u;
                            $updatedCount += $u;
                            Log::info('Updated stock_candle_daily for split (by last_datetime)', [
                                'symbol' => $r->symbol,
                                'stock_id' => $r->stock_id,
                                'date' => $dateKey,
                                'last_datetime' => $lastDatetime,
                                'close' => $entry['close'],
                                'volume' => $entry['volume'],
                            ]);
                        } else {
                            Log::info('No stock_candle_daily rows matched for update (by last_datetime)', [
                                'symbol' => $r->symbol,
                                'stock_id' => $r->stock_id,
                                'last_datetime' => $lastDatetime,
                                'close' => $entry['close'],
                                'volume' => $entry['volume'],
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('Error updating stock_candle_daily for split', ['exception' => $e, 'symbol' => $r->symbol, 'stock_id' => $r->stock_id, 'last_datetime' => $lastDatetime]);
                        // continue processing other dates
                    }
                }

                $results[] = [
                    'split' => $r,
                    'split_ratio' => $split_ratio,
                    'candle_found' => isset($candlePayload['s']) && $candlePayload['s'] === 'ok',
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'from_ts' => $fromTs,
                    'to_ts' => $toTs,
                    'finnhub_payload' => $candlePayload,
                    'candles' => $dailyCandles,
                    'updated_rows' => $affected,
                ];
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Error updating candles for splits', ['exception' => $e]);
            return response()->json(['message' => 'DB update failed', 'error' => $e->getMessage()], 500);
        }

    // Return the per-stock results directly so callers receive the detailed payloads
    return response()->json($results);
    }

    /**
     * Retrieve retroactive candles for a symbol up to (and including) a supplied date.
     *
     * Request/query params:
     * - symbol (required) e.g. AAPL
     * - date (required) yyyy-mm-dd (used as an upper bound; returns rows with ts <= date)
     * - limit (optional) number of rows to return, default 30 (most recent before date)
     *
     * Returns JSON: { message, symbol, date, limit, count, candles[] }
     */
    public function getRetroactiveCandles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string',
            'date' => 'required|date_format:Y-m-d',
            'limit' => 'sometimes|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $symbol = strtoupper($request->input('symbol'));
        $date = $request->input('date');
        $limit = (int) $request->input('limit', 30);

        // Resolve stock_id from stock_symbols
        $symbolRow = DB::table('stock_symbols')->where('symbol', $symbol)->first();
        if (!$symbolRow) {
            return response()->json(['message' => 'Symbol not found'], 404);
        }
        $stockId = $symbolRow->id;

        // Convert date to end-of-day string for comparison (assume stored ts is Y-m-d H:i:s)
        try {
            $upper = Carbon::createFromFormat('Y-m-d', $date)->endOfDay()->toDateTimeString();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid date format'], 422);
        }

        // Fetch candles retroactively (most recent rows with ts <= supplied date)
        try {
            $candles = DB::table('stock_candle_daily')
                ->where('stock_id', $stockId)
                ->where('ts', '<=', $upper)
                ->orderBy('ts', 'desc')
                ->limit($limit)
                ->get(['ts', 'open_price', 'high_price', 'low_price', 'close_price', 'volume']);
        } catch (\Exception $e) {
            Log::error('Error querying stock_candle_daily for retroactive candles', ['exception' => $e, 'symbol' => $symbol, 'date' => $date]);
            return response()->json(['message' => 'DB query failed', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'OK',
            'symbol' => $symbol,
            'date' => $date,
            'limit' => $limit,
            'count' => count($candles),
            'candles' => $candles,
        ]);
    }

    /**
     * Simple endpoint to fetch Finnhub /stock/candle with YYYY-MM-DD date inputs.
     * Query params: symbol (required), from (YYYY-MM-DD or epoch), to (YYYY-MM-DD or epoch)
     */
    public function getCandle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string',
            'from' => 'required',
            'to' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $symbol = $request->query('symbol');
        $from = $request->query('from');
        $to = $request->query('to');

        // Convert to epoch seconds. Accept either numeric epoch or YYYY-MM-DD
        $fromTs = is_numeric($from) ? (int)$from : (int) Carbon::parse($from)->startOfDay()->timestamp;
        $toTs = is_numeric($to) ? (int)$to : (int) Carbon::parse($to)->endOfDay()->timestamp;

        if (!$fromTs || !$toTs) {
            return response()->json(['message' => 'Invalid from/to date values'], 422);
        }

        // Hardcoded token as requested
        $token = 'ctukd71r01qg98tdggqgctukd71r01qg98tdggr0';

        try {
            $resp = Http::timeout(10)->get('https://finnhub.io/api/v1/stock/candle', [
                'symbol' => $symbol,
                'resolution' => 'D',
                'from' => $fromTs,
                'to' => $toTs,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            Log::error('Finnhub candle request failed', ['exception' => $e, 'symbol' => $symbol]);
            return response()->json(['message' => 'External request failed', 'error' => $e->getMessage()], 502);
        }

        if (!$resp->ok()) {
            return response()->json(['message' => 'Finnhub API error', 'status' => $resp->status(), 'body' => $resp->body()], $resp->status());
        }

        // Decode JSON and format if it contains candle arrays
        $payload = $resp->json();
        if (is_array($payload) && isset($payload['t']) && is_array($payload['t'])) {
            $times = $payload['t'] ?? [];
            $opens = $payload['o'] ?? [];
            $highs = $payload['h'] ?? [];
            $lows = $payload['l'] ?? [];
            $closes = $payload['c'] ?? [];
            $vols = $payload['v'] ?? [];

            $count = min(count($times), count($opens), count($highs), count($lows), count($closes), count($vols));
            $candles = [];
            for ($i = 0; $i < $count; $i++) {
                try {
                    $dt = Carbon::createFromTimestamp($times[$i])->toDateTimeString();
                } catch (\Exception $e) {
                    $dt = date('Y-m-d H:i:s', $times[$i]);
                }
                $candles[] = [
                    't' => (int) $times[$i],
                    'datetime' => $dt,
                    'o' => $opens[$i] ?? null,
                    'h' => $highs[$i] ?? null,
                    'l' => $lows[$i] ?? null,
                    'c' => $closes[$i] ?? null,
                    'v' => $vols[$i] ?? null,
                ];
            }

            // Return a formatted structure keeping original status and meta if present
            $out = [
                's' => $payload['s'] ?? null,
                'candles' => $candles,
            ];
            // include other meta fields if present (e.g., 's')
            if (isset($payload['s'])) {
                $out['s'] = $payload['s'];
            }

            // Attempt to upsert into stock_candle_daily by stock_id and ts
            $dbResult = [
                'stock_found' => false,
                'stock_id' => null,
                'updated' => 0,
                'inserted' => 0,
            ];

            try {
                $symbolRow = DB::table('stock_symbols')->where('symbol', strtoupper($symbol))->first();
                if ($symbolRow && isset($symbolRow->id)) {
                    $dbResult['stock_found'] = true;
                    $dbResult['stock_id'] = $symbolRow->id;
                    $stockId = $symbolRow->id;

                    foreach ($candles as $c) {
                        // The controller stores ts as a datetime string in many places
                        $tsString = $c['datetime'];

                        $row = [
                            'stock_id' => $stockId,
                            'ts' => $tsString,
                            'open_price' => $c['o'],
                            'high_price' => $c['h'],
                            'low_price' => $c['l'],
                            'close_price' => $c['c'],
                            'volume' => $c['v'],
                        ];

                        try {
                            $updated = DB::table('stock_candle_daily')
                                ->where('stock_id', $stockId)
                                ->where('ts', $tsString)
                                ->update([
                                    'open_price' => $c['o'],
                                    'high_price' => $c['h'],
                                    'low_price' => $c['l'],
                                    'close_price' => $c['c'],
                                    'volume' => $c['v'],
                                ]);
                        } catch (\Exception $e) {
                            Log::error('Error updating stock_candle_daily', ['exception' => $e, 'stock_id' => $stockId, 'ts' => $tsString]);
                            $updated = 0;
                        }

                        if ($updated) {
                            $dbResult['updated'] += $updated;
                        } else {
                            // insert if update didn't match any row
                            try {
                                DB::table('stock_candle_daily')->insert($row);
                                $dbResult['inserted'] += 1;
                            } catch (\Exception $e) {
                                Log::error('Error inserting stock_candle_daily', ['exception' => $e, 'stock_id' => $stockId, 'ts' => $tsString]);
                                // continue processing others
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error performing upsert for stock_candle_daily', ['exception' => $e, 'symbol' => $symbol]);
            }

            // attach db upsert summary to response
            $out['db'] = $dbResult;
             return response()->json($out, 200);
        }

        // Fallback: return original raw body
        return response($resp->body(), 200)->header('Content-Type', 'application/json');
    }
}