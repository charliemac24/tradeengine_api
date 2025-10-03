<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
// use Illuminate\Support\Facades\Log;
use App\Models\v1\Stock;
use App\Models\v1\StockIndicator;
use App\Models\v1\StockPrevIndicator; // enabled for saving previous indicator data

class IndicatorController extends Controller
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
     * Pull stock indicators batch based on the indicator type.
     *
     * @param Request $request
     * @param string $indicator
     * @return \Illuminate\Http\JsonResponse
     */
    public function pullStockIndicatorsBatch( string $indicator, Request $request)
    {
        $symbol = $request->input('symbol');

        switch ($indicator) {
            case 'ema50':
                $response = $this->getEma(50, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateEma50(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'ema100':
                $response = $this->getEma(100, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateEma100(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'ema200':
                $response = $this->getEma(200, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateEma200(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'ema10':
                $response = $this->getEma(10, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateEma10(new Request(['latest' => $latest, 'previous' => $previous]), $symbol);
                break;
            case 'sma10':
                $response = $this->getSma(10, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateSma10(new Request(['latest' => $latest, 'previous' => $previous]), $symbol);
                break;
            case 'sma50':
                $response = $this->getSma(50, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateSma50(new Request(['latest' => $latest, 'previous' => $previous]), $symbol);
                break;
            case 'sma20':
                $response = $this->getSma(20, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateSma20(new Request(['latest' => $latest, 'previous' => $previous]), $symbol);
                break;
            case 'sma200':
                $response = $this->getSma(200, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateSma200(new Request(['latest' => $latest, 'previous' => $previous]), $symbol);
                break;
            case 'sma100':
                $response = $this->getSma(100, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateSma100(new Request(['latest' => $latest, 'previous' => $previous]), $symbol);
                break;
            case 'plusdi':
                $response = $this->getPlusdi(3, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updatePlusdi(new Request(['latest' => $latest, 'previous' => $previous]), $symbol);
                break;
            case 'minusdi':
                $response = $this->getMinusdi(3, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateMinusdi(new Request(['latest' => $latest, 'previous' => $previous]), $symbol);
                break;
            case 'macd':
                $response = $this->getMacd(12, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateMacd(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'macdSignal':
                $response = $this->getMacdSignal(9, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateMacdSignalLine(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'macdHist':
                $response = $this->getMacdHist(9, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateMacdHist(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'rsi':
                $response = $this->getRsi(14, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateRsi(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'aroonUp':
                $response = $this->getAroonUp(14, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateAroonUp(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'aroonDown':
                $response = $this->getAroonDown(14, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateAroonDown(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'cci':
                $response = $this->getCci(20, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateCci(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'lowerB':
                $response = $this->getLowerb(20, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateLowerb(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'price':
                $response = $this->getPrice(1, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updatePrice(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'adx':
                $response = $this->getAdx(14, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateAdx(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'upperB':
                $response = $this->getUpperB(14, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateUpperband(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'middleB':
                $response = $this->getMiddleB(14, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateMiddleband(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'slowk':
                $response = $this->getSlowk(14, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateSlowk(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'slowd':
                $response = $this->getSlowd(14, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateSlowd(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;            
            case 'sar':
                $response = $this->getSar(14, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateSar(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'obv':
                $response = $this->getObv(14, $symbol);
                $latest = $response ? $response['latest'] : null;
                $previous = $response ? $response['previous'] : null;
                $this->updateObv(new Request(['latest' => $latest,'previous'=>$previous]), $symbol);
                break;
            case 'bullish':
                $this->updateBullish($symbol);
                break;
            case 'bearish':
                $this->updateBearish($symbol);
                break;
            default:
                return response()->json(['error' => 'Invalid indicator type'], 400);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Update the ema_50 indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEma50(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateEma50($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateEma50($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the ema_100 indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEma100(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {            
            $successLatest = StockIndicator::updateEma100($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateEma100($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the ema_200 indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEma200(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {            
            $successLatest = StockIndicator::updateEma200($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateEma200($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the ema_10 indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEma10(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateEma10($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateEma10($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }
    
    /**
     * Update the sma_10 indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSma10(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateSma10($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateSma10($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }
    
    /**
     * Update the sma_20 indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSma20(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateSma20($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateSma20($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the sma_50 indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSma50(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateSma50($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateSma50($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the sma_200 indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSma200(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateSma200($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateSma200($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }
    
    /**
     * Update the sma_100 indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSma100(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateSma100($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateSma100($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the macd indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMacd(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateMacd($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateMacd($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the macd_signal_line indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMacdSignalLine(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateMacdSignalLine($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateMacdSignalLine($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);            
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the macd_hist indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMacdHist(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateMacdHist($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateMacdHist($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the rsi indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRsi(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateRsi($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateRsi($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the lower b indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateLowerb(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateLowerb($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateLowerb($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the aroon_up indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAroonUp(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateAroonUp($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateAroonUp($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the aroon_down indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAroonDown(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateAroonDown($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateAroonDown($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the cci indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCci(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateCci($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateCci($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the price indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePrice(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updatePrice($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updatePrice($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the adx indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAdx(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateAdx($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateAdx($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the upper b indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUpperband(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateUpperband($stock->id, $request->input('latest'));
            $successPrev = StockPrevIndicator::updateUpperband($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    public function updateMiddleband(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateMiddleband($stock->id, $request->input('latest'));
            // $successPrev = StockPrevIndicator::updateMiddleband($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the slowk indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSlowk(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateSlowk($stock->id, $request->input('latest'));
            // $successPrev = StockPrevIndicator::updateSlowk($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the slowd indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSlowd(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateSlowd($stock->id, $request->input('latest'));
            // $successPrev = StockPrevIndicator::updateSlowd($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the sar indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSar(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateSar($stock->id, $request->input('latest'));
            // $successPrev = StockPrevIndicator::updateSar($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }

    /**
     * Update the obv indicator for a given stock symbol.
     *
     * @param Request $request
     * @param string $symbol
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateObv(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateObv($stock->id, $request->input('latest'));
            // $successPrev = StockPrevIndicator::updateObv($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }
    
    public function updatePlusdi(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updatePlusdi($stock->id, $request->input('latest'));
            // $successPrev = StockPrevIndicator::updatePlusdi($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
        }
    }
    
    
    public function updateMinusdi(Request $request, string $symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->first();

        if ($stock) {
            $successLatest = StockIndicator::updateMinusdi($stock->id, $request->input('latest'));
            // $successPrev = StockPrevIndicator::updateMinusdi($stock->id, $request->input('previous'));
            return response()->json(['success' => $successLatest]);
        } else {
            return response()->json(['error' => 'Stock symbol not found'], 404);
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

            // Previous indicators handling temporarily disabled
            // $indicatorsPrev = StockPrevIndicator::where('stock_id', $stock->id)->first();
            //
            // $bullish = [
            //     $indicatorsPrev->ema_50 > $indicatorsPrev->ema_200,
            //     $indicatorsPrev->macd > $indicatorsPrev->macd_signal_line,
            //     $indicatorsPrev->rsi >= 55 && $indicatorsPrev->rsi <= 75,
            //     $indicatorsPrev->aroon_up > 70 && $indicatorsPrev->aroon_down < 30,
            //     $indicatorsPrev->cci > 100,
            // ];
            // $bullish_counter = count(array_filter($bullish));
            // $indicatorsPrev->bullish = $bullish_counter >= 4 ? 1 : 0;
            // $successPrev = $indicatorsPrev->save();

            return response()->json(['success' => $successLatest]);
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

            // Previous indicators handling temporarily disabled
            // $indicatorsPrev = StockPrevIndicator::where('stock_id', $stock->id)->first();
            //
            // $bearish = [
            //     $indicatorsPrev->ema_50 < $indicatorsPrev->ema_200,
            //     $indicatorsPrev->macd < $indicatorsPrev->macd_signal_line,
            //     $indicatorsPrev->rsi >= 25 && $indicatorsPrev->rsi <= 45,
            //     $indicatorsPrev->aroon_up < 30 && $indicatorsPrev->aroon_down > 70,
            //     $indicatorsPrev->cci < -100,
            // ];
            //
            // $bearish_counter = count(array_filter($bearish));
            // $indicatorsPrev->bearish = $bearish_counter >= 4 ? 1 : 0;
            // $successPrev = $indicatorsPrev->save();

            return response()->json(['success' => $successLatest]);
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
        return $response ? array(
            'latest' => end($response['ema']),
            'previous' => array_slice($response['ema'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['sma']),
            'previous' => array_slice($response['sma'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['macd']),
            'previous' => array_slice($response['macd'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['macdSignal']),
            'previous' => array_slice($response['macdSignal'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['macdHist']),
            'previous' => array_slice($response['macdHist'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['rsi']),
            'previous' => array_slice($response['rsi'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['aroonup']),
            'previous' => array_slice($response['aroonup'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['aroondown']),
            'previous' => array_slice($response['aroondown'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['cci']),
            'previous' => array_slice($response['cci'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['lowerband']),
            'previous' => array_slice($response['lowerband'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['c']),
            'previous' => array_slice($response['c'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['adx']),
            'previous' => array_slice($response['adx'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['upperband']),
            'previous' => array_slice($response['upperband'], -2, 1)[0]
        ) : null;
    }

    public function getMiddleB($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'bbands', $timeperiod);
        return $response ? array(
            'latest' => end($response['middleband']),
            'previous' => array_slice($response['middleband'], -2, 1)[0]
        ) : null;
    }
    
    public function getPlusdi($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'plusdi', $timeperiod);
        return $response ? array(
            'latest' => end($response['plusdi']),
            'previous' => array_slice($response['plusdi'], -2, 1)[0]
        ) : null;
    }
    
    public function getMinusdi($timeperiod, $symbol)
    {
        $response = $this->fetchIndicatorData($symbol, 'minusdi', $timeperiod);
        return $response ? array(
            'latest' => end($response['minusdi']),
            'previous' => array_slice($response['minusdi'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['slowk']),
            'previous' => array_slice($response['slowk'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['slowd']),
            'previous' => array_slice($response['slowd'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['sar']),
            'previous' => array_slice($response['sar'], -2, 1)[0]
        ) : null;
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
        return $response ? array(
            'latest' => end($response['obv']),
            'previous' => array_slice($response['obv'], -2, 1)[0]
        ) : null;
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

        // Fetch just enough data for the indicator calculation
        $daysBack = $timeperiod == 200 ? 300 : 100; // At least 20 days for safety
        $params = [
            'symbol' => $symbol,
            'token' => $this->apiKey,
            'from' => strtotime("-{$daysBack} days"),
            'to' => $currentDayTimestamp,
            'indicator' => $indicator,
            'resolution' => 'D',
            'seriestype' => 'c',
            'timeperiod' => $timeperiod,
            'token' => $this->apiKey,
        ];
       
        //die();
        try {
            $response = Http::timeout(10)->get($this->apiBaseUrl . '/stock/candle', $params);

            if ($response->successful()) {
                $res = $response->json();

                // convert epoch timestamps in 't' to human-readable YYYY-mm-dd
                if (isset($res['t']) && is_array($res['t'])) {
                    foreach ($res['t'] as $i => $ts) {
                        $date = date('Y-m-d', (int) $ts);
                        $emaVal = isset($res['ema'][$i]) ? $res['ema'][$i] : null;
                        // debug output removed/commented earlier; keep minimal
                        $res['t'][$i] = $date;
                    }
                }

                return $res;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}