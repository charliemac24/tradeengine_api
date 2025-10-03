<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\v1\Stock;
use App\Models\v1\StockUpgradeDowngrade;

class StockUpgradeDowngradeController extends Controller
{


    /**
     * Get all upgrade/downgrade data with pagination
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllUpgradeDowngradesBkup(Request $request)
    {
        $userPlan = $request->input('userPlan', 'Guest Trader');

        /**if ($userPlan === 'Trade Engine Access') {
            $records = StockUpgradeDowngrade::where('created_at', '>=', $start = now()->subDays(7)->startOfDay())->get();
        } elseif ($userPlan === 'Trade Engine Professional') {
            $records = StockUpgradeDowngrade::all();
        } else {
            $records = collect(); // Empty collection
        }**/
        $records = StockUpgradeDowngrade::all();
        return response()->json($records);
    }

    /**
     * Get all upgrade/downgrade data with pagination (v2)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllUpgradeDowngrades(Request $request)
    {
        // Subquery to get latest upgrade/downgrade id per stock_id
        $latestIds = \DB::table('stock_upgrade_downgrade')
            ->selectRaw('MAX(id) as id')
            ->groupBy('stock_id');

        // Main query: join with stock_symbols and only get latest per stock
        $records = \DB::table('stock_upgrade_downgrade')
            ->join('stock_symbols', 'stock_upgrade_downgrade.stock_id', '=', 'stock_symbols.id')
            ->whereIn('stock_upgrade_downgrade.id', $latestIds)
            ->select(
                'stock_upgrade_downgrade.*',
                'stock_symbols.symbol as stock_symbol',
            )
            ->get();

        return response()->json($records);
    }

    /**
     * Get upgrade/downgrade data for a specific stock symbol
     * 
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUpgradeDowngradeBySymbol(string $symbol)
    {
        try {
            // Get stock ID from symbol
            $stock = Stock::where('symbol', $symbol)->first();
            
            if (!$stock) {
                return response()->json([
                    'error' => 'Stock not found',
                    'message' => "No stock found with symbol: {$symbol}"
                ], 404);
            }

            // Get upgrade/downgrade data for the stock
            $data = StockUpgradeDowngrade::getUpgradeDowngradeByStockId($stock->id);
            
            return response()->json([
                'symbol' => $symbol,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getUpgradeDowngradeBySymbol7Days(string $symbol)
        {
            try {
                // 1) Resolve stock by symbol
                $stock = Stock::where('symbol', strtoupper($symbol))->first();
                if (!$stock) {
                    return response()->json([
                        'error'   => 'Stock not found',
                        'message' => "No stock found with symbol: {$symbol}",
                    ], 404);
                }

                // 2) Compute 7-day window: today (end of day) back to 6 days ago (start of day)
                $end   = Carbon::today()->endOfDay();
                $start = Carbon::today()->subDays(6)->startOfDay(); // inclusive 7 days: today + previous 6

                // 3) Fetch last 7 days
                $recent = StockUpgradeDowngrade::query()
                    ->where('stock_id', $stock->id)
                    ->whereBetween('grade_time', [$start, $end])
                    ->orderBy('grade_time', 'desc')
                    ->get([
                        'id',
                        'stock_id',
                        'grade_time',
                        'company',
                        'from_grade',
                        'to_grade',
                        'action',
                        'created_at',
                        'updated_at',
                    ]);

                // 4) Fallback: if no rows in last 7 days, return the latest one
                if ($recent->isEmpty()) {
                    $latest = StockUpgradeDowngrade::query()
                        ->where('stock_id', $stock->id)
                        ->orderBy('grade_time', 'desc')
                        ->limit(1)
                        ->get([
                            'id',
                            'stock_id',
                            'grade_time',
                            'company',
                            'from_grade',
                            'to_grade',
                            'action',
                            'created_at',
                            'updated_at',
                        ]);

                    // Format the same as recent (array of rows)
                    return response()->json([
                        'symbol' => $stock->symbol,
                        'range'  => [
                            'type'  => 'fallback_latest',
                            'start' => null,
                            'end'   => null,
                        ],
                        'data'   => $latest->map(function ($row) use ($stock) {
                            return [
                                'symbol'     => $stock->symbol,
                                'date'       => Carbon::parse($row->grade_time)->toDateString(),
                                'firm'       => $row->company,
                                'from_grade' => $row->from_grade,
                                'to_grade'   => $row->to_grade,
                                'action'     => $row->action,
                            ];
                        })->values(),
                    ]);
                }

                // 5) Normal return: last 7 days
                return response()->json([
                    'symbol' => $stock->symbol,
                    'range'  => [
                        'type'  => 'past_7_days',
                        'start' => $start->toDateString(),
                        'end'   => $end->toDateString(),
                    ],
                    'data'   => $recent->map(function ($row) use ($stock) {
                        return [
                            'symbol'     => $stock->symbol,
                            'date'       => Carbon::parse($row->grade_time)->toDateString(),
                            'firm'       => $row->company,
                            'from_grade' => $row->from_grade,
                            'to_grade'   => $row->to_grade,
                            'action'     => $row->action,
                        ];
                    })->values(),
                ]);

            } catch (\Throwable $e) {
                return response()->json([
                    'error'   => 'Internal server error',
                    'message' => $e->getMessage(),
                ], 500);
            }
        }

}