<?php

namespace App\Http\Controllers\Indicators;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class RsiController extends Controller
{
    /**
     * The base URL of the Finnhub API.
     * @var string
     */
    private $apiBaseUrl;

    /**
     * Your Finnhub API key.
     * @var string
     */
    private $apiKey;

    /**
     * The name of the database table for this indicator.
     *
     * @var string
     */
    private $table;

    /**
     * The name of the indicator.
     * @var string
     */
    private $indicatorName;

    /**
     * The attribute name for the response data.
     * @var string
     */
    private $responseAttr;

    /**
     * The default time period for the indicator.
     * @var int
     */
    private $timeperiod;

    /**
     * The column name for the indicator value in the database.
     * @var string
     */
    private $columnName;

    /**
     * indicatorController constructor.
     */
    public function __construct()
    {
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey = config('services.finnhub.key');
        $this->table = 'rsi_indicator';
        $this->indicatorName = 'rsi';
        $this->responseAttr = 'rsi';
        $this->columnName = 'rsi';
        $this->timeperiod = 3;
    }

    /**
     * Get indicator indicator data for a given symbol.
     * @param string $symbol
     */
    public function getIndicator($symbol)
    {
        $symbol = strtoupper($symbol);
        $indicatorResponse = $this->fetchIndicatorData($symbol);
        $indicatorResponseAttr = $indicatorResponse->original[$this->responseAttr] ?? null;
        $indicatorResponseTimestamp = $indicatorResponse->original['t'] ?? null;

        return response()->json([
            'symbol' => $symbol,
            $this->columnName => $indicatorResponseAttr,
            'timestamps' => $indicatorResponseTimestamp
        ]);
    }

    /**
     * Fetch all indicator indicator data from Finnhub API.
     * @param string $symbol
     */
    private function fetchIndicatorData($symbol)
    {
        $currentDayTimestamp = strtotime('now');
        $from = strtotime('-90 days');
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
            'resolution' => 'D',
            'from' => $from,
            'to' => $currentDayTimestamp,
            'indicator' => $this->indicatorName,
            'timeperiod' => $this->timeperiod,
        ];

        try {
            $response = Http::timeout(15)->get($this->apiBaseUrl . '/stock/candle', $params);
            if ($response->successful()) {
                $json = $response->json();
                if (isset($json[$this->responseAttr]) && is_array($json[$this->responseAttr]) && count($json[$this->responseAttr]) > 0) {
                    $indicatorValues = array_filter($json[$this->responseAttr], function($v) { return $v !== null; });
                    $timestampValues = array_filter($json['t'], function($v) { return $v !== null; });
                    $indicatorResponse = !empty($indicatorValues) ? [$this->indicatorName => $indicatorValues, 't' => $timestampValues] : null;
                    if ($indicatorResponse) {
                        return response()->json($indicatorResponse);
                    }
                }
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Save indicator values to the database.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveToDb(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string'
        ]);

        // Example: $indicatorData = $this->getIndicator($symbol);
        $indicatorData = $this->getIndicator($request->input('symbol'));
   
        $symbol = $indicatorData->original['symbol'];
        $indicatorValues = $indicatorData->original[$this->responseAttr];
        $timestamps = $indicatorData->original['timestamps'];

        // Get stock_id from symbol
        $stock = DB::table('stock_symbols')->where('symbol', $symbol)->first();
        if (!$stock) {
            return response()->json(['error' => 'Stock symbol not found.'], 404);
        }
        $stock_id = $stock->id;

        $now = now();
        $rows = [];
        foreach ($indicatorValues as $i => $value) {
            $rows[] = [
                'stock_id'   => $stock_id,
                $this->columnName => $value,
                't'          => $timestamps[$i],
                't_date'          => date('Y-m-d', $timestamps[$i]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Upsert: unique by stock_id + t, update the stock_id and updated_at
        DB::table($this->table)->upsert(
            $rows,
            ['stock_id', 't'], // unique keys
            [$this->columnName, 'updated_at', 't_date'] 
        );

        return response()->json([
            'message' => 'indicator data upserted successfully.',
            'count' => count($rows),
            'symbol' => $symbol,
        ]);
    }
}