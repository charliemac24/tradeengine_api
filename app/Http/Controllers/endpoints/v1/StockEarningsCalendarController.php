<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\v1\Stock;
use App\Models\v1\StockEarningsCalendar;
use Illuminate\Support\Facades\DB;

class StockEarningsCalendarController extends Controller
{
    public function getAllEarningsCalendarBySymbol(string $symbol)
    {
        // Convert the symbol to uppercase
        $symbol = strtoupper($symbol);

        // Find the stock ID from the stock symbol
        $stock = Stock::where('symbol', $symbol)->first();

        if ($stock) {
            $data = StockEarningsCalendar::getEarningsCalendarByStockId($stock->id);
            return response()->json($data);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    public function getAllEarningsCalendar(Request $request)
    {
        $userPlan = $request->input('userPlan', 'Guest Trader');

        $daysBack = $request->input('days_back');

        /**if ($userPlan === 'Trade Engine Access') {
            $start = now()->subDays(3)->startOfDay();
            $end = now()->addDays(3)->endOfDay();
            $data = StockEarningsCalendar::whereBetween('cal_date', [$start, $end])->get();
        } elseif ($userPlan === 'Trade Engine Professional') {
            $data = StockEarningsCalendar::all();
        } else {
            $todayStart = now()->startOfDay();
            $todayEnd = now()->endOfDay();
            $data = StockEarningsCalendar::whereBetween('cal_date', [$todayStart, $todayEnd])->get();
        } **/

        $data = StockEarningsCalendar::getAllEarningsCalendar($daysBack);       
        return response()->json($data);
        
       // $data = StockEarningsCalendar::getAllEarningsCalendar();
       // return response()->json($data);
    }

    public function getAllEarningsCalendarMarketCapLimit()
    {
        
        $data = StockEarningsCalendar::getAllEarningsCalendarMarketCapLimit();
        return response()->json($data);
    }

    /**
     * Hide past or today's earnings with missing EPS or revenue data.
     *
     * This will set 'hide' = 1 for all records where:
     * - cal_date is today or earlier
     * - AND (eps_actual IS NULL OR eps_estimate IS NULL OR revenue_actual IS NULL OR revenue_estimate IS NULL)
     */
    public function hideIncompletePastEarnings()
    {
        $today = now()->toDateString();

        $affected = DB::table('stock_earnings_calendar')
            ->whereDate('cal_date', '<', $today)
            ->where(function ($query) {
                $query->whereNull('eps_actual')
                      ->orWhereNull('revenue_actual');
            })
            ->update(['hide' => 1]);

        return response()->json([
            'message' => 'Records updated.',
            'affected_rows' => $affected
        ]);
    }
}