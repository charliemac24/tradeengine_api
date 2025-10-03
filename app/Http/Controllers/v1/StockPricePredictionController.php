<?php

namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockPricePredictionController extends Controller
{
    /**
     * Update the actual_price_monthly field in price_prediction table
     * where next_month_prediction_date <= now, using the latest close_price
     * from stock_candle_monthly for each stock.
     */
    public function updateActualPriceMonthly()
    {
        $sql = "UPDATE price_prediction pp
            INNER JOIN stock_symbols ss ON pp.company = ss.symbol
            INNER JOIN stock_candle_daily scd ON scd.stock_id = ss.id AND scd.ts = pp.next_month_prediction_date
            SET pp.actual_price_monthly = scd.close_price
            WHERE pp.next_month_prediction_date <= NOW()";

        $updated = DB::update($sql);

        return response()->json([
            'message' => 'actual_price_monthly updated successfully',
            'rows_updated' => $updated,
        ]);
    }
    /**
     * Store a new stock price prediction.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'AI_Model' => 'required|string',
            'Company' => 'required|string',
            'Last_date' => 'required|date',
            'Next_day_Prediction_date' => 'required|date',
            'Next_day_Prediction_price' => 'required|numeric',
            'Next_week_Prediction_date' => 'required|date',
            'Next_week_Prediction_price' => 'required|numeric',
            'Next_month_Prediction_date' => 'required|date',
            'Next_month_Prediction_price' => 'required|numeric',
            'Total_Tokens' => 'required|integer',
            'API_cost' => 'required|string',
        ]);

        DB::table('price_prediction')->insert([
            'ai_model' => $validated['AI_Model'],
            'company' => $validated['Company'],
            'last_date' => $validated['Last_date'],
            'next_day_prediction_date' => $validated['Next_day_Prediction_date'],
            'next_day_prediction_price' => $validated['Next_day_Prediction_price'],
            'next_week_prediction_date' => $validated['Next_week_Prediction_date'],
            'next_week_prediction_price' => $validated['Next_week_Prediction_price'],
            'next_month_prediction_date' => $validated['Next_month_Prediction_date'],
            'next_month_prediction_price' => $validated['Next_month_Prediction_price'],
            'actual_price_daily'=>0,
            'actual_price_weekly'=>0,
            'actual_price_monthly'=>0,
            'total_tokens' => $validated['Total_Tokens'],
            'api_cost' => $validated['API_cost'],
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Stock price prediction saved successfully.']);
     }

     /**
     * Fetch stock price predictions.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetch(Request $request)
    {
        // Optional: filter by company or model if needed
        $query = DB::table('price_prediction');

        if ($request->has('company')) {
            $query->where('company', $request->input('company'));
        }
        if ($request->has('ai_model')) {
            $query->where('ai_model', $request->input('ai_model'));
        }

        $predictions = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['predictions' => $predictions]);
    }

    public function fetchSingle(string $symbol)
    {
        // Optional: filter by company or model if needed
        $query = DB::table('stock_price_prediction');
        $query->where('stock', $symbol);

        $predictions = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['predictions' => $predictions]);
    }

    /**
     * Update the actual_price_daily field in price_prediction table
     * where next_day_prediction_date <= now, using the latest close_price
     * from stock_candle_daily for each stock.
     */
    public function updateActualPriceDaily()
    {
        // Update actual_price_daily using the stock_candle_daily.close_price where
        // the candle timestamp equals the next_day_prediction_date, and that date is in the past.
        // We join price_prediction -> stock_symbols -> stock_candle_daily to resolve stock_id.
        $sql = "UPDATE price_prediction pp
            INNER JOIN stock_symbols ss ON pp.company = ss.symbol
            INNER JOIN stock_candle_daily scd ON scd.stock_id = ss.id AND scd.ts = pp.next_day_prediction_date
            SET pp.actual_price_daily = scd.close_price
            WHERE pp.next_day_prediction_date <= NOW()";

        $updated = DB::update($sql);

        return response()->json([
            'message' => 'actual_price_daily updated successfully',
            'rows_updated' => $updated,
        ]);
    }
    /**
     * Update the actual_price_weekly field in price_prediction table
     * where next_week_prediction_date <= now, using the latest close_price
     * from stock_candle_daily for each stock.
     */
    public function updateActualPriceWeekly()
    {
        $sql = "UPDATE price_prediction pp
            INNER JOIN stock_symbols ss ON pp.company = ss.symbol
            INNER JOIN stock_candle_daily scd ON scd.stock_id = ss.id AND scd.ts = pp.next_week_prediction_date
            SET pp.actual_price_weekly = scd.close_price
            WHERE pp.next_week_prediction_date <= NOW()";

        $updated = DB::update($sql);

        return response()->json([
            'message' => 'actual_price_weekly updated successfully',
            'rows_updated' => $updated,
        ]);
    }
}