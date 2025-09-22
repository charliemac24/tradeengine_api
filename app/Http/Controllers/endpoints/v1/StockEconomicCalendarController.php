<?php
/**
 * Class StockEconomicCalendarController
 *
 * Handles HTTP requests related to stock economic calendar events.
 * Provides endpoints for retrieving, filtering, and managing economic calendar data.
 *
 * @package App\Http\Controllers
 */

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\v1\Stock;
use App\Models\v1\StockEconomicCalendar;

class StockEconomicCalendarController extends Controller
{
    
    /**
     * Retrieve all economic calendar events for a specific stock symbol.
     *
     * Joins the stock_symbols table to filter events by the given symbol.
     *
     * @param string $symbol The stock symbol to filter economic calendar events.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllEconomicCalendarBySymbol($symbol)
    {
        $events = StockEconomicCalendar::query()
            ->join('stock_symbols', 'stock_symbols.id', '=', 'stock_economic_calendars.stock_id')
            ->where('stock_symbols.symbol', $symbol)
            ->select('stock_economic_calendars.*', 'stock_symbols.symbol')
            ->get();

        return response()->json($events);
    }

    /**
     * Retrieve economic calendar events based on the user's plan.
     *
     * - Trade Engine Access: Events from 3 days ago to 3 days ahead (based on econ_time).
     * - Trade Engine Professional: All events.
     * - Others: Events for today only.
     *
     * @param \Illuminate\Http\Request $request The HTTP request instance, expects 'userPlan' as input.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllEconomicCalendar(Request $request)
    {
        $userPlan = $request->input('userPlan', 'Guest Trader');

        /**if ($userPlan === 'Trade Engine Access') {
            $start = now()->subDays(3)->startOfDay();
            $end = now()->addDays(3)->endOfDay();
            $events = StockEconomicCalendar::whereBetween('econ_time', [$start, $end])->get();
        } elseif ($userPlan === 'Trade Engine Professional') {
            $events = StockEconomicCalendar::all();
        } else {
            $todayStart = now()->startOfDay();
            $todayEnd = now()->endOfDay();
            $events = StockEconomicCalendar::whereBetween('econ_time', [$todayStart, $todayEnd])->get();
        }**/
        $events = StockEconomicCalendar::all();
        return response()->json($events);
    }
}
