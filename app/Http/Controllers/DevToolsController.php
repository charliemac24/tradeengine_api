<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DevToolsController extends Controller
{
    private function authorizeToken(Request $request)
    {
        if ($request->query('token') !== env('STOCKS_BATCH_TOKEN')) {
            abort(403, 'Unauthorized');
        }
    }

    public function triggerBatch1(Request $request)
    {
        $this->authorizeToken($request);
        // placeholder if you want to call a command
        // Artisan::call('stocks:batch');
        return response('Stocks batch 1 triggered', 200);
    }

    public function triggerBatch2(Request $request)
    {
        $this->authorizeToken($request);
        Artisan::call('stocks:batch2');
        return response('Stocks batch 2 triggered', 200);
    }

    public function triggerBatch3(Request $request)
    {
        $this->authorizeToken($request);
        Artisan::call('stocks:batch3');
        return response('Stocks batch 3 triggered', 200);
    }

    public function triggerBatch4(Request $request)
    {
        $this->authorizeToken($request);
        Artisan::call('stocks:batch4');
        return response('Stocks batch 4 triggered', 200);
    }

    public function triggerBatch5(Request $request)
    {
        $this->authorizeToken($request);
        Artisan::call('stocks:batch5');
        return response('Stocks batch 5 triggered', 200);
    }

    public function historicalIndicators1(Request $request)
    {
        $this->authorizeToken($request);
        Artisan::call('stocks:historical-indicators1');
        return response('Stocks historical indicators 1 triggered', 200);
    }

    public function historicalIndicators2(Request $request)
    {
        $this->authorizeToken($request);
        Artisan::call('stocks:historical-indicators2');
        return response('Stocks historical indicators 2 triggered', 200);
    }

    public function processFundamentals(Request $request)
    {
        $this->authorizeToken($request);
        Artisan::call('stocks:fundamentals');
        return response('Stocks process fundamentals triggered', 200);
    }
}
