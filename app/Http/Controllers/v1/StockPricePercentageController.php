<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\v1\Stock;
use App\Models\v1\StockPriceMetric;

class StockPricePercentageController extends Controller
{
    /**
     * Return top 10 stocks by percentage using each stock's latest record (by closing_date).
     * Joined to stock_symbols and stocks_by_market_cap and filters where stocks_by_market_cap.notpriority = 0
     * Also includes close_price from the latest stock_candle_daily row per stock (latest by ts).
     * Also joins stock_trading_score on symbol to include trade_engine_score.
     * Attaches related stock_company_news within the last 24 hours in an array variable named "news".
     */
    public function topTenLatestPerStock(Request $request)
    {
        $latestPerStock = DB::table('stock_percentage_daily')
            ->select('stock_id', DB::raw('MAX(closing_date) as max_date'))
            ->groupBy('stock_id');

        $latestCandlePerStock = DB::table('stock_candle_daily')
            ->select('stock_id', DB::raw('MAX(ts) as max_ts'))
            ->groupBy('stock_id');

        $latestRows = DB::table('stock_percentage_daily as s')
            ->joinSub($latestPerStock, 't', function ($join) {
                $join->on('s.stock_id', '=', 't.stock_id')
                     ->on('s.closing_date', '=', 't.max_date');
            })
            ->join('stock_symbols', 'stock_symbols.id', '=', 's.stock_id')
            ->join('stocks_by_market_cap', 'stock_symbols.symbol', '=', 'stocks_by_market_cap.symbol')
            ->joinSub($latestCandlePerStock, 'ct', function ($join) {
                $join->on('s.stock_id', '=', 'ct.stock_id');
            })
            ->join('stock_candle_daily as c', function ($join) {
                $join->on('c.stock_id', '=', 'ct.stock_id')
                     ->on('c.ts', '=', 'ct.max_ts');
            })
            ->join('stock_trading_score as tscore', 'tscore.symbol', '=', 'stock_symbols.symbol')
            ->where('stocks_by_market_cap.notpriority', 0)
            ->select(
                's.stock_id',
                'stock_symbols.symbol',
                's.percentage',
                'c.close_price AS last_price',
                's.closing_date',
                'tscore.trade_engine_score'
            )
            ->orderByDesc('s.percentage')
            ->limit(10)
            ->get();

        // collect news for the fetched stock_ids within last 24 hours
        $stockIds = $latestRows->pluck('stock_id')->unique()->values()->all();
        $since = Carbon::now()->subDay();
        $newsGrouped = [];
        if (!empty($stockIds)) {
            $newsGrouped = DB::table('stock_company_news')
                ->whereIn('stock_id', $stockIds)
                ->where('date_time', '>=', $since)
                ->orderByDesc('date_time')
                ->get()
                ->groupBy('stock_id')
                ->map(function ($g) { return $g->values()->toArray(); })
                ->toArray();
        }

        // attach news array to each row and also expose the grouped news array as $news
        $latestRows = $latestRows->map(function ($r) use ($newsGrouped) {
            $r->news = $newsGrouped[$r->stock_id] ?? [];
            return $r;
        })->values();

        $news = $newsGrouped; // array variable requested

        return response()->json($latestRows, 200);
    }

    /**
     * Return worst 10 stocks by percentage using each stock's latest record (by closing_date).
     * Uses same joins/filters as topTenLatestPerStock but returns the lowest percentages (worst performers).
     * Attaches related stock_company_news within the last 24 hours in an array variable named "news".
     */
    public function worstTenLatestPerStock(Request $request)
    {
        $latestPerStock = DB::table('stock_percentage_daily')
            ->select('stock_id', DB::raw('MAX(closing_date) as max_date'))
            ->groupBy('stock_id');

        $latestCandlePerStock = DB::table('stock_candle_daily')
            ->select('stock_id', DB::raw('MAX(ts) as max_ts'))
            ->groupBy('stock_id');

        $rows = DB::table('stock_percentage_daily as s')
            ->joinSub($latestPerStock, 't', function ($join) {
                $join->on('s.stock_id', '=', 't.stock_id')
                     ->on('s.closing_date', '=', 't.max_date');
            })
            ->join('stock_symbols', 'stock_symbols.id', '=', 's.stock_id')
            ->join('stocks_by_market_cap', 'stock_symbols.symbol', '=', 'stocks_by_market_cap.symbol')
            ->joinSub($latestCandlePerStock, 'ct', function ($join) {
                $join->on('s.stock_id', '=', 'ct.stock_id');
            })
            ->join('stock_candle_daily as c', function ($join) {
                $join->on('c.stock_id', '=', 'ct.stock_id')
                     ->on('c.ts', '=', 'ct.max_ts');
            })
            ->join('stock_trading_score as tscore', 'tscore.symbol', '=', 'stock_symbols.symbol')
            ->where('stocks_by_market_cap.notpriority', 0)
            ->select(
                's.stock_id',
                'stock_symbols.symbol',
                's.percentage',
                'c.close_price AS last_price',
                's.closing_date',
                'tscore.trade_engine_score'
            )
            ->orderBy('s.percentage', 'asc')
            ->limit(10)
            ->get();

        $stockIds = $rows->pluck('stock_id')->unique()->values()->all();
        $since = Carbon::now()->subDay();
        $newsGrouped = [];
        if (!empty($stockIds)) {
            $newsGrouped = DB::table('stock_company_news')
                ->whereIn('stock_id', $stockIds)
                ->where('date_time', '>=', $since)
                ->orderByDesc('date_time')
                ->get()
                ->groupBy('stock_id')
                ->map(function ($g) { return $g->values()->toArray(); })
                ->toArray();
        }

        $rows = $rows->map(function ($r) use ($newsGrouped) {
            $r->news = $newsGrouped[$r->stock_id] ?? [];
            return $r;
        })->values();

        $news = $newsGrouped;

        return response()->json($rows, 200);
    }
}
