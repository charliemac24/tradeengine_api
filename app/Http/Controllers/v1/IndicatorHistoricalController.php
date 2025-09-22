<?php
// filepath: c:\Users\Charlie\Projects\docker-trendseeker-server-laravel\application\app\Http\Controllers\v1\IndicatorHistoricalController.php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Models\v1\Stock;
use App\Models\v1\StockIndicator;
use Illuminate\Support\Facades\Http;

class IndicatorHistoricalController extends Controller
{
    /**
     * The base URL of the Finnhub API.
     *
     * @var string
     */
    private $apiBaseUrl;

    /**
     * Your Finnhub API key.
     *
     * @var string
     */
    private $apiKey;

    /**
     * IndicatorController constructor.
     */
    public function __construct()
    {
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey = config('services.finnhub.key');
    }

    /**
     * Insert or update stock indicator data for a stock_id.
     * If the latest row for the stock_id is within 9:30am-5pm EST today, update it.
     * Otherwise, insert a new row.
     */
    public function upsertIndicator(string $indicator, Request $request)
    {
        $request->validate([
            'symbol' => 'required|string'
            // Add validation for your indicator fields as needed
        ]);
        $symbol = $request->input('symbol');
        $nowEst = Carbon::now('America/New_York');
        $todayEst = $nowEst->toDateString();     

        $indicatorColumn = $this->getIndicatorColumnName($indicator);
        

        // Define today's trading window in EST
        $startTimeEst = Carbon::parse("$todayEst 01:30:00", 'America/New_York');
        $endTimeEst = Carbon::parse("$todayEst 04:00:00", 'America/New_York');
        $startTimeUtc = $startTimeEst->copy()->setTimezone('UTC');
        $endTimeUtc = $endTimeEst->copy()->setTimezone('UTC');

        // Get stock_id from the database
        $stockId = DB::table('stock_symbols')
            ->where('symbol', $symbol)
            ->value('id');  
            
        // Get the latest record for this stock_id within today's trading window
        $latest = DB::table('stock_historical_indicators_v2')
            ->where('stock_id', $stockId)
            ->whereBetween('created_at', [$startTimeUtc, $endTimeUtc])
            ->whereNotNull($indicatorColumn)
            ->orderByDesc('created_at')
            ->first();
        
        // If trading hour has started and no record for this stock_id yet for today, insert new record
        if ($nowEst->greaterThanOrEqualTo($startTimeEst) && $nowEst->lessThanOrEqualTo($endTimeEst) && !$latest) {
            
            $finalData = [];
            $data = $this->getStockIndicator($indicator,$symbol);
            $finalData['stock_id'] = $stockId;
            $finalData['created_at'] = $nowEst->copy()->setTimezone('UTC');
            $finalData['updated_at'] = now();

            foreach($data as $key=>$value){
                foreach($value as $item=>$v){
                    $finalData[$key] = $v;
                    $id = DB::table('stock_historical_indicators_v2')->insertGetId($finalData);
                }
            }           

            return response()->json(['message' => 'New indicator row inserted for today\'s trading window.', 'id' => $id]);
        }

        // If trading hour and record exists, update
        if ($nowEst->greaterThanOrEqualTo($startTimeEst) && $nowEst->lessThanOrEqualTo($endTimeEst) && $latest) {
            $data = $this->getStockIndicator($indicator,$symbol);
            foreach($data as $key => $value) {
                if (is_array($value)) {
                    // If it's an array, get the last value or first value
                    $data[$key] = end($value);
                }
            }
            $value = $data[$indicatorColumn] ?? null;

DB::table('stock_historical_indicators_v2')
    ->where('stock_id', $stockId)
    ->whereBetween('created_at', [$startTimeUtc, $endTimeUtc])
    ->update([
        $indicatorColumn => $value,
        'updated_at' => now(),
    ]);

            echo "<pre>";
            print_r($data);
            echo "</pre>";
            die();

            return response()->json(['message' => 'Indicator updated for today\'s trading window.', 'id' => $latest->id]);
        }

        return response()->json(['message' => 'Trading hour not started or already handled for today.'], 200);
    }

    private function getIndicatorColumnName($indicator)
    {
        // Map your indicator string to the actual DB column name
        $map = [
            'ema50' => 'ema_50',
            'ema100' => 'ema_100',
            'ema200' => 'ema_200',
            'ema10' => 'ema_10',
            'sma10' => 'sma_10',
            'sma50' => 'sma_50',
            'sma20' => 'sma_20',
            'sma200' => 'sma_200',
            'sma100' => 'sma_100',
            // ...add the rest as needed
        ];
        return $map[$indicator] ?? $indicator;
    }
    
    /**
     * Fetch the stock indicator data based on the request parameters.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function getStockIndicator($indicator,$symbol)
    {       

        if (!$symbol) {
            return response()->json(['error' => 'Invalid parameter'], 400);
        }

        if (!$indicator) {
            return response()->json(['error' => 'Indicator type is required'], 400);
        }
        
        switch ($indicator) {
            case 'ema50':
                return ['ema_50' => $this->getEma(50, $symbol)];
            case 'ema100':
                return ['ema_100' => $this->getEma(100, $symbol)];
            case 'ema200':
                return ['ema_200' => $this->getEma(200, $symbol)];
            case 'ema10':
                return ['ema_10' => $this->getEma(10, $symbol)];
            case 'sma10':
                return ['sma_10' => $this->getSma(10, $symbol)];
            case 'sma50':
                return ['sma_50' => $this->getSma(50, $symbol)];
            case 'sma20':
                return ['sma_20' => $this->getSma(20, $symbol)];
            case 'sma200':
                return ['sma_200' => $this->getSma(200, $symbol)];
            case 'sma100':
                return ['sma_100' => $this->getSma(100, $symbol)];
            case 'plusdi':
                return ['plus_di' => $this->getPlusdi(3, $symbol)];
            case 'minusdi':
                return ['minus_di' => $this->getMinusdi(3, $symbol)];
            case 'macd':
                return ['macd' => $this->getMacd(12, $symbol)];
            case 'macdSignal':
                return ['macd_signal_line' => $this->getMacdSignal(9, $symbol)];
            case 'macdHist':
                return ['macd_hist' => $this->getMacdHist(9, $symbol)];
            case 'rsi':
                return ['rsi' => $this->getRsi(14, $symbol)];
            case 'aroonUp':
                return ['aroon_up' => $this->getAroonUp(14, $symbol)];
            case 'aroonDown':
                return ['aroon_down' => $this->getAroonDown(14, $symbol)];
            case 'cci':
                return ['cci' => $this->getCci(20, $symbol)];
            case 'lowerB':
                return ['lower_b' => $this->getLowerb(20, $symbol)];
            case 'price':
                return ['price' => $this->getPrice(1, $symbol)];
            case 'adx':
                return ['adx' => $this->getAdx(14, $symbol)];
            case 'upperB':
                return ['upperband' => $this->getUpperB(14, $symbol)];
            case 'slowk':
                return ['slowk' => $this->getSlowk(14, $symbol)];
            case 'slowd':
                return ['slowd' => $this->getSlowd(14, $symbol)];
            case 'sar':
                return ['sar' => $this->getSar(14, $symbol)];
            case 'obv':
                return ['obv' => $this->getObv(14, $symbol)];
            case 'bullish':
                $this->updateBullish($symbol);
                break;
            case 'bearish':
                $this->updateBearish($symbol);
                break;
            default:
                return response()->json(['error' => 'Invalid indicator type'], 400);
        }
    }

    /**
     * Update the bullish indicator for a given stock symbol.
     *
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBullish(string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {

            $indicators = StockIndicator::where('stock_id', $stock->id)->first();

            $bullish = [
                $indicators->ema_50 > $indicators->ema_200,
                $indicators->macd > $indicators->macd_signal_line,
                $indicators->rsi >= 55 && $indicators->rsi <= 75,
                $indicators->aroon_up > 70 && $indicators->aroon_down < 30,
                $indicators->cci > 100,
            ];
            $bullish_counter = count(array_filter($bullish));
            $indicators->bullish = $bullish_counter >= 4 ? 1 : 0;
            $successLatest = $indicators->save();

            // Previous indicators
            $indicatorsPrev = StockPrevIndicator::where('stock_id', $stock->id)->first();

            $bullish = [
                $indicatorsPrev->ema_50 > $indicatorsPrev->ema_200,
                $indicatorsPrev->macd > $indicatorsPrev->macd_signal_line,
                $indicatorsPrev->rsi >= 55 && $indicatorsPrev->rsi <= 75,
                $indicatorsPrev->aroon_up > 70 && $indicatorsPrev->aroon_down < 30,
                $indicatorsPrev->cci > 100,
            ];
            $bullish_counter = count(array_filter($bullish));
            $indicatorsPrev->bullish = $bullish_counter >= 4 ? 1 : 0;
            $successPrev = $indicatorsPrev->save();

            return response()->json(['success' => $successLatest && $successPrev]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the bearish indicator for a given stock symbol.
     *
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBearish(string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $indicators = StockIndicator::where('stock_id', $stock->id)->first();

            $bearish = [
                $indicators->ema_50 < $indicators->ema_200,
                $indicators->macd < $indicators->macd_signal_line,
                $indicators->rsi >= 25 && $indicators->rsi <= 45,
                $indicators->aroon_up < 30 && $indicators->aroon_down > 70,
                $indicators->cci < -100,
            ];

            $bearish_counter = count(array_filter($bearish));
            $indicators->bearish = $bearish_counter >= 4 ? 1 : 0;
            $successLatest = $indicators->save();

            // Previous indicators
            $indicatorsPrev = StockPrevIndicator::where('stock_id', $stock->id)->first();

            $bearish = [
                $indicatorsPrev->ema_50 < $indicatorsPrev->ema_200,
                $indicatorsPrev->macd < $indicatorsPrev->macd_signal_line,
                $indicatorsPrev->rsi >= 25 && $indicatorsPrev->rsi <= 45,
                $indicatorsPrev->aroon_up < 30 && $indicatorsPrev->aroon_down > 70,
                $indicatorsPrev->cci < -100,
            ];

            $bearish_counter = count(array_filter($bearish));
            $indicatorsPrev->bearish = $bearish_counter >= 4 ? 1 : 0;
            $successPrev = $indicatorsPrev->save();

            return response()->json(['success' => $successLatest && $successPrev]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Fetch EMA indicator data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @param string $data_version
     * @return float|null
     */
    public function getEma($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'ema', $timeperiod);
        return $response ? $response['ema'] : null;
    }

    /**
     * Fetch SMA indicator data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return array|null
     */
    public function getSma($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'sma', $timeperiod);
        return $response ? $response['sma'] : null;
    }

    /**
     * Fetch MACD indicator data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getMacd($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'macd', $timeperiod);
        return $response ? $response['macd'] : null;
    }

    /**
     * Fetch MACD SIGNAL indicator data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getMacdSignal($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'macd', $timeperiod);
        return $response ? $response['macdSignal'] : null;
    }

    /**
     * Fetch MACD HIST indicator data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getMacdHist($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'macd', $timeperiod);
        return $response ? $response['macdHist'] : null;
    }

    /**
     * Fetch RSI indicator data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getRsi($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'rsi', $timeperiod);
        return $response ? $response['rsi'] : null;
    }

    /**
     * Fetch AROON UP indicator data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getAroonUp($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'aroon', $timeperiod);
        return $response ? $response['aroonup'] : null;
    }

    /**
     * Fetch AROON DOWN indicator data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getAroonDown($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'aroon', $timeperiod);
        return $response ? $response['aroondown'] : null;
    }

    /**
     * Fetch CCI indicator data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getCci($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'cci', $timeperiod);
        return $response ? $response['cci'] : null;
    }

    /**
     * Fetch Lower B indicator data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getLowerb($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'bbands', $timeperiod);
        return $response ? $response['lowerband'] : null;
    }

    /**
     * Fetch Price data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getPrice($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'ema', $timeperiod);
        return $response ? $response['c'] : null;
    }

    /**
     * Fetch adx data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getAdx($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'adx', $timeperiod);
        return $response ? $response['adx'] : null;
    }

    /**
     * Fetch upper b data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getUpperB($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'bbands', $timeperiod);
        return $response ? $response['upperband'] : null;
    }
    
    public function getPlusdi($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'plusdi', $timeperiod);
        return $response ? $response['plusdi'] : null;
    }
    
    public function getMinusdi($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'minusdi', $timeperiod);
        return $response ? $response['minusdi'] : null;
    }

    /**
     * Fetch Slowk data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getSlowk($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'STOCH', $timeperiod);
        return $response ? $response['slowk'] : null;
    }

    /**
     * Fetch Slowd data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getSlowd($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'STOCH', $timeperiod);
        return $response ? $response['slowd'] : null;
    }

    /**
     * Fetch Sar data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getSar($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'sar', $timeperiod);
        return $response ? $response['sar'] : null;
    }

    /**
     * Fetch Obv data.
     *
     * @param int $timeperiod
     * @param string $symbol
     * @return float|null
     */
    public function getObv($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'obv', $timeperiod);
        return $response ? $response['obv'] : null;
    }

    /**
     * Generic method to fetch indicator data from the Finnhub API.
     *
     * @param string $symbol
     * @param string $indicator
     * @param int $timeperiod
     * @return array|null
     */
    private function fetchIndicatorData($symbol, $indicator, $timeperiod)
    {
        $currentDayTimestamp = strtotime('now');
        $from = strtotime('-365 days');
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
            'from' => $from,
            'to' => $currentDayTimestamp,
            'indicator' => $indicator,
            'resolution' => 'D',
            'seriestype' => 'c',
            'timeperiod' => $timeperiod,
        ];
        //echo date("Y-m-d H:i:s", $from) . "<br>";
        //echo date("Y-m-d H:i:s", $currentDayTimestamp) . "<br>";
        //echo $this->apiBaseUrl . '/stock/candle'. '?' . http_build_query($params) . "\n";
        //die();
        try {
            $response = Http::timeout(10)->get($this->apiBaseUrl . '/stock/candle', $params);
              
            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            
            return null;
        }
    }
}