<?php

namespace App\Http\Controllers\Indicators;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LatestController extends Controller
{
    private $apiBaseUrl;
    private $apiKey;

    public function __construct()
    {
        // Initialize any required properties or dependencies here
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey = config('services.finnhub.key');
    }
    
    public function fetchIndicatorData(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string',
            'indicator' => 'required|string',
            'resolution' => 'nullable|string',
            'from' => 'nullable|integer',
            'to' => 'nullable|integer',
            'timeperiod' => 'nullable|integer|min:1|max:100',
        ]);

        $symbol = $request->input('symbol');
        $indicator = $request->input('indicator');
        $resolution = $request->input('resolution', 'D');
        $from = $request->input('from', strtotime('-30 days'));
        $to = $request->input('to', time());
        $timeperiod = $request->input('timeperiod', 3);

        $response = Http::timeout(15)->get($this->apiBaseUrl . '/stock/candle', [
            'symbol' => $symbol,
            'indicator' => $indicator,
            'resolution' => $resolution,
            'from' => $from,
            'to' => $to,
            'token' => $this->apiKey,
            'timeperiod' => $timeperiod,
        ]);
        echo "<pre>";
        print_r($response->json());
        echo "</pre>";
        die();
        return $response->json();
    }
}