<?php
// filepath: c:\Users\Charlie\AppData\Local\Temp\fz3temp-2\Bootstrap.php

namespace App\Http\Controllers\Indicators;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\v1\StockCandleDaily;

class BootstrapController extends Controller
{
  
    public function allIndicators(Request $request)
    {
        // Validate and get symbol, from, and to from request
        $request->validate([
            'symbol' => 'required|string',
            'from' => 'date',
            'to' => 'date',
        ]);
        $symbol = $request->input('symbol');
        $from = $request->input('from');
        $to = $request->input('to');

        // Get stock_id from stock_symbols table
        $stockId = DB::table('stock_symbols')->where('symbol', $symbol)->value('id');
        if (!$stockId) {
            return response()->json(['error' => 'Stock symbol not found.'], 404);
        }

        // Set default date range if not provided
        if (!$from && !$to) {
            $from = now()->subDays(30)->toDateString();
            $to = now()->toDateString();
        }

        // Join all indicator tables on stock_id and t_date, and filter by t_date in ema50_indicator
        $indicators = DB::table('rsi_indicator')
            ->leftJoin('lowerb_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'lowerb_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'lowerb_indicator.t_date');
            })
            ->leftJoin('macd_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'macd_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'macd_indicator.t_date');
            })
            ->leftJoin('minusdi_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'minusdi_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'minusdi_indicator.t_date');
            })
            ->leftJoin('obv_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'obv_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'obv_indicator.t_date');
            })
            ->leftJoin('plusdi_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'plusdi_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'plusdi_indicator.t_date');
            })
            // ema50 is referenced in select; join its table
            ->leftJoin('ema50_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'ema50_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'ema50_indicator.t_date');
            })
            ->leftJoin('sma50_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'sma50_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'sma50_indicator.t_date');
            })
            ->leftJoin('adx_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'adx_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'adx_indicator.t_date');
            })
            ->leftJoin('upperb_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'upperb_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'upperb_indicator.t_date');
            })
            ->leftJoin('price_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'price_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'price_indicator.t_date');
            })
            // add social sentiments join: for each calendar date pick the latest at_time's score for that stock
            ->leftJoin(DB::raw("(SELECT s.stock_id, DATE(s.at_time) AS at_date, s.score
                                 FROM stock_social_sentiments s
                                 JOIN (
                                     SELECT DATE(at_time) AS at_date, MAX(at_time) AS max_at
                                     FROM stock_social_sentiments
                                     WHERE stock_id = {$stockId}
                                     GROUP BY DATE(at_time)
                                 ) m ON DATE(s.at_time) = m.at_date AND s.at_time = m.max_at
                                 WHERE s.stock_id = {$stockId}
                                ) AS sss"),
                       function($join) {
                           // join by calendar date only (ignore time component)
                           $join->on(DB::raw('DATE(rsi_indicator.t_date)'), '=', DB::raw('sss.at_date'));
                       })
             ->join('stock_candle_daily', function($join) {
                 $join->on('rsi_indicator.stock_id', '=', 'stock_candle_daily.stock_id')
                      ->on('rsi_indicator.t_date', '=', 'stock_candle_daily.ts');
             })
            ->where('rsi_indicator.stock_id', $stockId)
            ->orderBy('rsi_indicator.t_date')
            ->select(
                'rsi_indicator.t_date',
                'ema50_indicator.ema as ema50',
                'lowerb_indicator.lowerband as lowerb',
                'macd_indicator.macd as macd',
                'minusdi_indicator.minusdi as minusdi',
                'obv_indicator.obv as obv',
                'plusdi_indicator.plusdi as plusdi',
                'rsi_indicator.rsi as rsi',
                'sma50_indicator.sma as sma50',
                'adx_indicator.adx as adx',
                'upperb_indicator.upperband as upperb',
                'price_indicator.price as price',
                // social sentiment score from historical table (per t_date) - use alias from subquery
                'sss.score as social_sentiment_score',
                'stock_candle_daily.close_price',
                'stock_candle_daily.high_price',
                'stock_candle_daily.low_price',
                'stock_candle_daily.open_price',
                'stock_candle_daily.volume'
            );

        // Date filtering
        $indicators->whereBetween('rsi_indicator.t_date', [$from, $to]);

        $indicators = $indicators->get();

        // Replace numeric zero values with 'n/a' for readability in the API response.
        // We skip the date field since it should remain a date string.
        $indicators = collect($indicators)->map(function ($row) {
            foreach ($row as $key => $value) {
                if ($key === 't_date') {
                    continue;
                }

                // Treat integer/float zeros and their string forms as empty/unavailable
                if ($value === 0 || $value === 0.0 || $value === '0' || $value === '0.0') {
                    $row->$key = 'n/a';
                }
            }

            return $row;
        })->values();

        // Query price target data for the given stock_id
        $priceTarget = DB::table('stock_price_target')
            ->where('stock_id', $stockId)
            ->select('target_high', 'target_low', 'target_median')
            ->first();

        // Query trading scores by symbol
        $scoreRow = DB::table('stock_trading_score')
            ->where('symbol', $symbol)
            ->select(
                'fundamental_score',
                'news_sentiment_score',
                'social_sentiment_score',
                'analyst_score'
            )
            ->first();

        $scores = $scoreRow ? (array) $scoreRow : [];

        // determine latest t_date among returned indicators
        $latestDate = null;
        if (count($indicators) > 0) {
            // compute latest calendar date (ignore time-of-day)
            $latestDate = collect($indicators)
                            ->map(function($r){ return substr($r->t_date,0,10); })
                            ->max();
        }
 
        // Merge score fields into each indicator row.
        // fundamental_score, news_sentiment_score, and analyst_score are "N/A" for all dates
        // except the latest t_date where the real values are applied.
        $indicators = collect($indicators)->map(function ($row) use ($scores, $latestDate) {
            $rowArr = (array) $row;
 
            // Start with the row values (which include historical social_sentiment_score).
            $merged = $rowArr;
 
            // Extract social_sentiment_score now, remove it from merged so we can append it last
            $social = $rowArr['social_sentiment_score'] ??
                      ($scores['social_sentiment_score'] ?? 'N/A');
            
 
            // For other score fields, apply N/A except on latest date (compare calendar date only)
            $rowDate = substr($row->t_date,0,10);
            if ($latestDate === null || ($rowDate !== $latestDate)) {
                 $merged['fundamental_score'] = 'N/A';
                 $merged['news_sentiment_score'] = 'N/A';
                 $merged['analyst_score'] = 'N/A';
             } else {
                 // on latest date pull values from the aggregated score row, fallback to 'N/A'
                 $merged['fundamental_score'] = $scores['fundamental_score'] ?? 'N/A';
                 $merged['news_sentiment_score'] = $scores['news_sentiment_score'] ?? 'N/A';
                 $merged['analyst_score'] = $scores['analyst_score'] ?? 'N/A';
             }
 
             // append social_sentiment_score last
             $merged['social_sentiment_score'] = $social;
 
             return (object) $merged;
         })->values();

        return response()->json([
            'stock_id' => $stockId,
            'symbol' => $symbol,
            'indicators' => $indicators,
            'price_target' => $priceTarget,
        ]);
    }

    public function allIndicatorsWithScores(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string',
            'from' => 'date',
            'to' => 'date',
        ]);
        $symbol = $request->input('symbol');
        $from = $request->input('from');
        $to = $request->input('to');

        $stockId = DB::table('stock_symbols')->where('symbol', $symbol)->value('id');
        if (!$stockId) {
            return response()->json(['error' => 'Stock symbol not found.'], 404);
        }

        if (!$from && !$to) {
            $from = now()->subDays(30)->toDateString();
            $to = now()->toDateString();
        }

        $indicators = DB::table('rsi_indicator')
            ->leftJoin('lowerb_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'lowerb_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'lowerb_indicator.t_date');
            })
            ->leftJoin('macd_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'macd_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'macd_indicator.t_date');
            })
            ->leftJoin('minusdi_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'minusdi_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'minusdi_indicator.t_date');
            })
            ->leftJoin('obv_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'obv_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'obv_indicator.t_date');
            })
            ->leftJoin('plusdi_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'plusdi_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'plusdi_indicator.t_date');
            })
            ->leftJoin('ema50_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'ema50_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'ema50_indicator.t_date');
            })
            ->leftJoin('sma50_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'sma50_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'sma50_indicator.t_date');
            })
            ->leftJoin('adx_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'adx_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'adx_indicator.t_date');
            })
            ->leftJoin('upperb_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'upperb_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'upperb_indicator.t_date');
            })
            ->leftJoin('price_indicator', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'price_indicator.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'price_indicator.t_date');
            })
            // pick the latest social sentiment per calendar date for this stock
            ->leftJoin(DB::raw("(SELECT s.stock_id, DATE(s.at_time) AS at_date, s.score
                                 FROM stock_social_sentiments s
                                 JOIN (
                                     SELECT DATE(at_time) AS at_date, MAX(at_time) AS max_at
                                     FROM stock_social_sentiments
                                     WHERE stock_id = {$stockId}
                                     GROUP BY DATE(at_time)
                                 ) m ON DATE(s.at_time) = m.at_date AND s.at_time = m.max_at
                                 WHERE s.stock_id = {$stockId}
                                ) AS sss"),
                       function($join) {
                           $join->on(DB::raw('DATE(rsi_indicator.t_date)'), '=', DB::raw('sss.at_date'));
                       })
            ->join('stock_candle_daily', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'stock_candle_daily.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'stock_candle_daily.ts');
            })
            ->where('rsi_indicator.stock_id', $stockId)
            ->orderBy('rsi_indicator.t_date')
            ->select(
                'rsi_indicator.t_date',
                'price_indicator.price as price',
                'sss.score as social_sentiment_score',
                'stock_candle_daily.close_price',
                'stock_candle_daily.high_price',
                'stock_candle_daily.low_price',
                'stock_candle_daily.open_price',
                'stock_candle_daily.volume'
            )
            ->whereBetween('rsi_indicator.t_date', [$from, $to]);

        $indicators = $indicators->get();

        // keep values as-is but prepare scores
        $priceTarget = DB::table('stock_price_target')->where('stock_id', $stockId)
            ->select('target_high', 'target_low', 'target_median')->first();

        $scoreRow = DB::table('stock_trading_score')->where('symbol', $symbol)
            ->select('fundamental_score','news_sentiment_score','social_sentiment_score','analyst_score')
            ->first();
        $scores = $scoreRow ? (array)$scoreRow : [];

        $latestDate = null;
        if (count($indicators) > 0) {
            $latestDate = collect($indicators)->map(function($r){ return substr($r->t_date,0,10); })->max();
        }

        $indicators = collect($indicators)->map(function ($row) use ($scores, $latestDate) {
            $rowArr = (array)$row;
            $merged = $rowArr;

            // pick social from row (historical) or fallback to overall score
            $social = $rowArr['social_sentiment_score'] ?? ($scores['social_sentiment_score'] ?? 'N/A');
            

            $rowDate = substr($row->t_date,0,10);
            if ($latestDate === null || ($rowDate !== $latestDate)) {
                $merged['fundamental_score'] = 'N/A';
                $merged['news_sentiment_score'] = 'N/A';
                $merged['analyst_score'] = 'N/A';
            } else {
                $merged['fundamental_score'] = $scores['fundamental_score'] ?? 'N/A';
                $merged['news_sentiment_score'] = $scores['news_sentiment_score'] ?? 'N/A';
                $merged['analyst_score'] = $scores['analyst_score'] ?? 'N/A';
            }

            // append social last
            $merged['social_sentiment_score'] = $social;

            return (object)$merged;
        })->values();

 
        return response()->json([
            'stock_id' => $stockId,
            'symbol' => $symbol,
            'indicators' => $indicators,
            'price_target' => $priceTarget,
        ]);
    }

    public function allIndicatorsPricesOnly(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string',
            'from' => 'date',
            'to' => 'date',
        ]);
        $symbol = $request->input('symbol');
        $from = $request->input('from');
        $to = $request->input('to');

        $stockId = DB::table('stock_symbols')->where('symbol', $symbol)->value('id');
        if (!$stockId) {
            return response()->json(['error' => 'Stock symbol not found.'], 404);
        }

        if (!$from && !$to) {
            $from = now()->subDays(30)->toDateString();
            $to = now()->toDateString();
        }

        $rows = DB::table('rsi_indicator')
            ->join('stock_candle_daily', function($join) {
                $join->on('rsi_indicator.stock_id', '=', 'stock_candle_daily.stock_id')
                     ->on('rsi_indicator.t_date', '=', 'stock_candle_daily.ts');
            })
            ->where('rsi_indicator.stock_id', $stockId)
            ->orderBy('rsi_indicator.t_date')
            ->select(
                'rsi_indicator.t_date',
                'stock_candle_daily.close_price',
                'stock_candle_daily.high_price',
                'stock_candle_daily.low_price',
                'stock_candle_daily.open_price',
                'stock_candle_daily.volume'
            )
            ->whereBetween('rsi_indicator.t_date', [$from, $to])
            ->get();

        return response()->json([
            'stock_id' => $stockId,
            'symbol' => $symbol,
            'prices' => $rows,
        ]);
    }
}




