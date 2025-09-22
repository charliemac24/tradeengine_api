<?php
/**
 * Class StockInvestmentReportController
 *
 * Handles HTTP requests related to stock investment analyst reports.
 * Provides endpoints for saving new analyst reports and retrieving existing reports.
 *
 * @package App\Http\Controllers\v1
 */

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockInvestmentReportController extends Controller
{
    /**
     * Save a new stock investment analyst report.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing report data.
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveAnalystReport(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string|max:20',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        DB::table('stock_investment_analyst_report')->insert([
            'user_id' => $request->input('user_id'), // Assuming user is authenticated
            'symbol' => $request->input('symbol'),
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Report saved successfully.'], 201);
    }

    /**
     * Retrieve a list of stock investment analyst reports.
     *
     * @param \Illuminate\Http\Request $request The HTTP request instance.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAnalystReports(Request $request)
    {      
        $reports = DB::table('stock_investment_analyst_report')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['reports' => $reports]);
    }
}