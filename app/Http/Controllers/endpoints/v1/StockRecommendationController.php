<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use App\Models\v1\Stock;
use App\Models\v1\StockRecommendationTrends;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StockRecommendationController extends Controller
{
    /**
     * Get recommendation trends for a specific stock symbol.
     *
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStockRecommendationBySymbol(string $symbol)
    {
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);
        
        // Get the stock ID
        $stockId = Stock::where('symbol', $symbol)->value('id');
        
        if (!$stockId) {
            return response()->json(['error' => 'Stock not found'], 404);
        }
        
        // Get the recommendation trends data
        $recommendations = StockRecommendationTrends::getRecommendationTrendsByStockId($stockId);
        
        if (empty($recommendations)) {
            return response()->json(['error' => 'Recommendation data not found'], 404);
        }
        
        return response()->json($recommendations);
    }

    /**
     * Get all stock recommendation trends with pagination.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllStockRecommendations(Request $request)
    {
        $limit = $request->query('limit', 50);
        $offset = $request->query('offset', 0);
        
        $recommendations = StockRecommendationTrends::getAllRecommendationTrends($limit, $offset);
        
        return response()->json($recommendations);
    }

    public function getStockRecommendationBySymbol7days(string $symbol)
    {
        $symbol  = strtoupper($symbol);

        // 1) Resolve stock id
        $stockId = Stock::where('symbol', $symbol)->value('id');
        if (!$stockId) {
            return response()->json(['error' => 'Stock not found'], 404);
        }

        // 2) Define 7-day window (inclusive)
        $end   = Carbon::today()->endOfDay();
        $start = Carbon::today()->subDays(6)->startOfDay(); // today + prior 6 = 7 days

        // 3) Fetch rows ordered by the most meaningful date field
        //    We don't know whether the table uses `date` or `period`,
        //    so order by COALESCE(date, period) desc.
        $rows = StockRecommendationTrends::query()
            ->where('stock_id', $stockId)
            ->orderByRaw('`period` DESC')
            ->get();
        if ($rows->isEmpty()) {
            return response()->json(['error' => 'Recommendation data not found'], 404);
        }

        // 4) Filter to last 7 days (by whichever date field exists per row)
        $recent = $rows->filter(function ($row) use ($start, $end) {
            $d = $row->date ?? $row->period ?? null;
            if (!$d) return false;
            try {
                $dt = Carbon::parse($d);
                return $dt->between($start, $end);
            } catch (\Throwable $e) {
                return false;
            }
        });

        // 5) Choose payload set: recent window or fallback latest (one row)
        $selected = $recent->isNotEmpty() ? $recent : $rows->take(1);

        // 6) Normalize output shape & date (Y-m-d)
        $data = $selected->map(function ($r) use ($symbol) {
            $rawDate = $r->date ?? $r->period ?? null;
            $dateStr = null;
            if ($rawDate) {
                try { $dateStr = Carbon::parse($rawDate)->toDateString(); } catch (\Throwable $e) { $dateStr = (string) $rawDate; }
            }

            return [
                'symbol'       => $symbol,
                'date'         => $dateStr,
                'buy'          => isset($r->buy) ? (int) $r->buy : null,
                'hold'         => isset($r->hold) ? (int) $r->hold : null,
                'sell'         => isset($r->sell) ? (int) $r->sell : null,
                'strong_buy'   => isset($r->strongBuy)   ? (int) $r->strongBuy   : (isset($r->strong_buy) ? (int) $r->strong_buy : null),
                'strong_sell'  => isset($r->strongSell)  ? (int) $r->strongSell  : (isset($r->strong_sell) ? (int) $r->strong_sell : null),
            ];
        })->values();

        // 7) Build response with range meta
        return response()->json([
            'symbol' => $symbol,
            'range'  => $recent->isNotEmpty()
                ? ['type' => 'past_7_days', 'start' => $start->toDateString(), 'end' => $end->toDateString()]
                : ['type' => 'fallback_latest', 'start' => null, 'end' => null],
            'data'   => $data,
        ]);
    }
} 