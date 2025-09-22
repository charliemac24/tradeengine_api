<?php

namespace App\Http\Controllers\Endpoints\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Class StockScreenerController
 *
 * This controller handles the stock screener endpoint for retrieving,
 * processing, and returning stocks data according to various filters supplied
 * by the front-end.
 *
 * @package App\Http\Controllers\Endpoints\v1
 */
class StockScreenerController extends Controller
{
    /**
     * Retrieves and processes stock data based on input parameters and returns
     * the results in JSON format.
     *
     * The method builds a complex query with various joins and conditions based on:
     * - Basic fundamentals
     * - Basic technicals
     * - Advanced technicals
     * - Advanced fundamentals
     * - Sector, market cap, price and performance filters
     *
     * The result is cached for 350 seconds to improve performance.
     *
     * @param Request $request The HTTP request containing filtering parameters.
     * @return \Illuminate\Http\JsonResponse JSON response containing the screening results.
     */
    public function getScreenerResult(Request $request)
    {
        // Enable query logging for debugging purposes.
        DB::enableQueryLog();

        // Retrieve inputs from the request
        $basicFundamental   = $request->input('basic_fundamental', '');
        $basicTechnical     = $request->input('basic_technical', '');
        $advanceTechnical   = $request->input('advance_technical', '');
        $advanceFundamental = $request->input('advance_fundamental', '');
        $stockSector        = $request->input('ssector', '');
        $marketCap          = $request->input('market_cap', '');
        $price              = $request->input('price', '');
        $performance        = $request->input('performance', '');

        $page    = (int)$request->input('page', 1);
        $perPage = (int)$request->input('per_page', 20);
        $offset  = ($page - 1) * $perPage;

        // Determine candle and percentage tables based on performance
        if (strpos($performance, 'daily') !== false) {
            $candleTable     = 'stock_candle_daily';
            $percentageTable = 'stock_percentage_daily';
        } elseif (strpos($performance, 'weekly') !== false) {
            $candleTable     = 'stock_candle_weekly';
            $percentageTable = 'stock_percentage_weekly';
        } elseif (strpos($performance, 'monthly') !== false) {
            $candleTable     = 'stock_candle_monthly';
            $percentageTable = 'stock_percentage_monthly';
        } else {
            $candleTable     = 'stock_candle_daily';
            $percentageTable = 'stock_percentage_daily';
        }

        // Build the base query with multiple joins
        $query = DB::table('stock_symbols')
            ->distinct()
            ->join('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')
            ->leftJoin('stock_quote', 'stock_quote.stock_id', '=', 'stock_symbols.id')
            ->leftJoin('stock_dividend_quarterly', 'stock_symbols.id', '=', 'stock_dividend_quarterly.stock_id')
            ->leftJoin('stock_indicators', 'stock_symbols.id', '=', 'stock_indicators.stock_id')
            ->leftJoin(
                DB::raw("(
                    SELECT stock_id, trans_val 
                    FROM stock_insiders 
                    WHERE (stock_id, trans_date) IN (
                        SELECT stock_id, MAX(trans_date) 
                        FROM stock_insiders 
                        GROUP BY stock_id
                    )
                ) as latest_insider"),
                'stock_symbols.id',
                '=',
                'latest_insider.stock_id'
            )
            ->leftJoin('stock_news_sentiments', 'stock_symbols.id', '=', 'stock_news_sentiments.stock_id')
            ->leftJoin(
                DB::raw("(
                    SELECT stock_id, close_price, volume 
                    FROM {$candleTable}
                    WHERE (stock_id, ts) IN (
                        SELECT stock_id, MAX(ts) 
                        FROM {$candleTable} 
                        GROUP BY stock_id
                    )
                ) as latest_candle"),
                'stock_symbols.id',
                '=',
                'latest_candle.stock_id'
            )
            ->leftJoin(
                DB::raw("(
                    SELECT stock_id, percentage 
                    FROM {$percentageTable}
                    WHERE (stock_id, closing_date) IN (
                        SELECT stock_id, MAX(closing_date) 
                        FROM {$percentageTable} 
                        GROUP BY stock_id
                    )
                ) as latest_percentage"),
                'stock_symbols.id',
                '=',
                'latest_percentage.stock_id'
            )
            ->leftJoin('stock_basic_financials_metric', 'stock_symbols.id', '=', 'stock_basic_financials_metric.stock_id')
            ->leftJoin('stock_prev_indicators', 'stock_symbols.id', '=', 'stock_prev_indicators.stock_id')
            ->leftJoin(
                DB::raw("(
                    SELECT stock_id, value, percentage 
                    FROM stock_institutional_ownership
                    WHERE (stock_id, id) IN (
                        SELECT stock_id, MAX(id)
                        FROM stock_institutional_ownership 
                        GROUP BY stock_id
                    )
                ) as latest_institutional_ownership"),
                'stock_symbols.id',
                '=',
                'latest_institutional_ownership.stock_id'
            )
            ->leftJoin('stock_price_metrics', 'stock_symbols.id', '=', 'stock_price_metrics.stock_id')
            ->leftJoin('stock_price_target', 'stock_symbols.id', '=', 'stock_price_target.stock_id')
            ->leftJoin(
                DB::raw("(
                    SELECT stock_id, strongBuy, sell 
                    FROM stock_recommendation_trends 
                    WHERE (stock_id, id) IN (
                        SELECT stock_id, MAX(id) 
                        FROM stock_recommendation_trends 
                        GROUP BY stock_id
                    )
                ) as latest_stock_recommendation_trends"),
                'stock_symbols.id',
                '=',
                'latest_stock_recommendation_trends.stock_id'
            )
            ->leftJoin(
                DB::raw("(
                    SELECT stock_id, score 
                    FROM stock_company_earnings_quality_score 
                    WHERE (stock_id, period) IN (
                        SELECT stock_id, MAX(period)
                        FROM stock_company_earnings_quality_score 
                        GROUP BY stock_id
                    )
                ) as latest_stock_company_earnings_quality_score"),
                'stock_symbols.id',
                '=',
                'latest_stock_company_earnings_quality_score.stock_id'
            )
            ->select(
                'stock_symbols.id',
                'stock_symbols.symbol',
                'stock_quote.current_price',
                'stock_symbol_info.sector',
                'latest_institutional_ownership.value',
                'latest_institutional_ownership.percentage',
                'stock_symbol_info.industry',
                'stock_symbol_info.market_cap',
                'stock_symbol_info.company_name',
                'latest_candle.close_price AS price',
                'latest_percentage.percentage AS change',
                'latest_candle.volume'
            )
            ->where('stock_symbol_info.company_name', '!=', '')
            ->where('stock_symbol_info.sector', '!=', '')
            ->where('stock_symbol_info.industry', '!=', '');

        /*
         * Basic Fundamental Screeners
         */
        switch ($basicFundamental) {
            case 'bargain_bin_finder':
                $query->where('stock_basic_financials_metric.pettm', '<', 15)
                      ->where('stock_basic_financials_metric.pbannual', '<', 1.5)
                      ->where('stock_basic_financials_metric.psttm', '<', 2)
                      ->where('stock_basic_financials_metric.pfcfsharettm', '<', 10);
                break;
            case 'power_profit_picks':
                $query->where('stock_basic_financials_metric.roettm', '>=', 15)
                      ->where('stock_basic_financials_metric.roattm', '>=', 8)
                      ->where('stock_basic_financials_metric.operatingmarginttm', '>=', 12)
                      ->where('stock_basic_financials_metric.netprofitmarginttm', '>=', 10);
                break;
            case 'rising_stars_tracker':
                $query->where('stock_basic_financials_metric.epsgrowth5y', '>=', 10)
                      ->where('stock_basic_financials_metric.revenuegrowth5y', '>=', 10)
                      ->where('stock_basic_financials_metric.bookvaluesharegrowth5y', '>=', 5);
                break;
            case 'dividend_dollar_hunter':
                $query->where('stock_basic_financials_metric.currentdividendyieldttm', '>=', 3)
                      ->where('stock_basic_financials_metric.dividendgrowthrate5y', '>=', 5)
                      ->where('stock_basic_financials_metric.payoutratiottm', '<=', 70);
                break;
            case 'smart_combo_stock_picker':
                $query->where('stock_basic_financials_metric.pettm', '<', 15)
                      ->where('stock_basic_financials_metric.pbannual', '<', 1.5)
                      ->where('stock_basic_financials_metric.roettm', '>=', 15)
                      ->where('stock_basic_financials_metric.netprofitmarginttm', '>=', 10);
                break;
            case 'make_it_double':
                $query->where('stock_basic_financials_metric.epsgrowth5y', '>=', 10)
                      ->where('stock_basic_financials_metric.revenuegrowth5y', '>=', 10)
                      ->where('stock_basic_financials_metric.dividendgrowthrate5y', '>=', 5)
                      ->where('stock_basic_financials_metric.payoutratiottm', '<=', 70)
                      ->where('stock_basic_financials_metric.currentdividendyieldttm', '>=', 3);
                break;
            case 'stocks_built_like_tanks':
                $query->where('stock_basic_financials_metric.currentratioannual', '>=', 1.5)
                      ->where('stock_basic_financials_metric.quickratioannual', '>=', 1)
                      ->where('stock_basic_financials_metric.totaldebt_totalequityannual', '<', 1)
                      ->where('stock_basic_financials_metric.cashflowpersharettm', '>', 0);
                break;
        }

        /*
         * Basic Technical Screeners
         */
        switch ($basicTechnical) {
            case 'bullish_momentum_reversal':
                $query->where('stock_prev_indicators.rsi', '<', 30)
                      ->where('stock_indicators.rsi', '>', 30)
                      ->where('stock_prev_indicators.price', '<', DB::raw('stock_prev_indicators.ema_10'))
                      ->where('stock_indicators.price', '>', DB::raw('stock_indicators.ema_10'));
                break;
            case 'strong_trend_continuation':
                $query->where('stock_indicators.price', '>', DB::raw('stock_indicators.sma_50'))
                      ->where('stock_prev_indicators.macd_hist', '<', 0)
                      ->where('stock_indicators.macd_hist', '>', 0);
                break;
            case 'bearish_reversal_alert':
                $query->where('stock_prev_indicators.rsi', '>', 70)
                      ->where('stock_indicators.rsi', '<', 70)
                      ->where('stock_prev_indicators.price', '>', DB::raw('stock_prev_indicators.sma_50'))
                      ->where('stock_indicators.price', '<', DB::raw('stock_indicators.sma_50'));
                break;
            case 'golden_cross_momentum_confirmation':
                $query->where('stock_prev_indicators.rsi', '<', DB::raw('stock_prev_indicators.sma_50'))
                      ->where('stock_indicators.sma_50', '>', DB::raw('stock_indicators.sma_200'))
                      ->where('stock_indicators.rsi', '>', 55);
                break;
            case 'breakout_watch':
                $query->where('stock_indicators.price', '>', DB::raw('stock_basic_financials_metric.fifty_two_week_high'))
                      ->where('latest_candle.volume', '>', DB::raw('stock_basic_financials_metric.ten_day_ave_trading_vol'));
                break;
            case 'bearish_macd_divergence':
                $query->where('stock_prev_indicators.macd', '>', DB::raw('stock_prev_indicators.macd_signal_line'))
                      ->where('stock_indicators.macd', '<', DB::raw('stock_indicators.macd_signal_line'))
                      ->where('stock_prev_indicators.price', '>', DB::raw('stock_prev_indicators.ema_20'))
                      ->where('stock_indicators.price', '<', DB::raw('stock_indicators.ema_20'));
                break;
            case 'bullish_bollinger_band_reversal':
                $query->where('stock_prev_indicators.price', '<', DB::raw('stock_prev_indicators.lower_b'))
                      ->where('stock_indicators.price', '>', DB::raw('stock_indicators.lower_b'))
                      ->where('stock_indicators.rsi', '<', 35);
                break;
        }

        /*
         * Advanced Technical Screeners
         */
        switch ($advanceTechnical) {
            case 'bullish_cross_climb':
                $query->where('stock_indicators.sma_20', '>', DB::raw('stock_indicators.sma_50'))
                      ->where('stock_indicators.macd', '>', 0)
                      ->where('stock_indicators.macd_hist', '>', 0)
                      ->where('stock_indicators.rsi', '<', 70);
                break;
            case 'bearish_cross_climb':
                $query->where('stock_indicators.sma_20', '<', DB::raw('stock_indicators.sma_50'))
                      ->where('stock_indicators.macd', '<', 0)
                      ->where('stock_indicators.macd_hist', '<', 0)
                      ->where('stock_indicators.rsi', '>', 70);
                break;
            case 'bullish_bollinger_bounce':
                $query->where('stock_indicators.price', '<', DB::raw('stock_indicators.lower_b'))
                      ->where('stock_indicators.adx', '<', 20)
                      ->where('stock_indicators.rsi', '<', 30);
                break;
            case 'bearish_bollinger_bounce':
                $query->where('stock_indicators.price', '<', DB::raw('stock_indicators.upperband'))
                      ->where('stock_indicators.adx', '<', 20)
                      ->where('stock_indicators.rsi', '>', 70);
                break;
            case 'bullish_swingsurge':
                $query->where('stock_indicators.slowk', '>', DB::raw('stock_indicators.slowd'))
                      ->where('stock_prev_indicators.slowk', '<', 20)
                      ->where('stock_indicators.slowk', '>', 20)
                      ->where('stock_indicators.rsi', '>', 50)
                      ->where('stock_prev_indicators.rsi', '<', 50)
                      ->where('stock_indicators.aroon_up', '>', 70)
                      ->where('stock_indicators.aroon_down', '<', 30);
                break;
            case 'bearish_swingsurge':
                $query->where('stock_indicators.slowk', '<', DB::raw('stock_indicators.slowd'))
                      ->where('stock_prev_indicators.slowk', '>', 80)
                      ->where('stock_indicators.slowk', '<', 80)
                      ->where('stock_indicators.rsi', '<', 50)
                      ->where('stock_prev_indicators.rsi', '>', 50)
                      ->where('stock_indicators.aroon_down', '>', 70)
                      ->where('stock_indicators.aroon_up', '<', 30);
                break;
            case 'bullish_trend_tracker':
                $query->where('stock_indicators.cci', '>', -100)
                      ->where('stock_prev_indicators.sar', '>', DB::raw('stock_prev_indicators.price'))
                      ->where('stock_indicators.sar', '<', DB::raw('stock_indicators.price'))
                      ->where('stock_indicators.adx', '>', 20);
                break;
            case 'bearish_trend_tracker':
                $query->where('stock_indicators.cci', '<', 100)
                      ->where('stock_prev_indicators.sar', '<', DB::raw('stock_prev_indicators.price'))
                      ->where('stock_indicators.sar', '>', DB::raw('stock_indicators.price'))
                      ->where('stock_indicators.adx', '>', 20);
                break;
            case 'bullish_volume_vision':
                $query->where('stock_indicators.sma_10', '>', DB::raw('stock_indicators.sma_50'))
                      ->where('stock_indicators.sma_50', '>', DB::raw('stock_indicators.sma_100'))
                      ->where('stock_indicators.macd_hist', '>', 0)
                      ->orWhere('stock_indicators.macd_hist', '>', DB::raw('stock_prev_indicators.macd_hist'))
                      ->where('stock_indicators.obv', '>', DB::raw('stock_prev_indicators.obv'));
                break;
            case 'bearish_volume_vision':
                $query->where('stock_indicators.sma_10', '<', DB::raw('stock_indicators.sma_50'))
                      ->where('stock_indicators.sma_50', '<', DB::raw('stock_indicators.sma_100'))
                      ->where('stock_indicators.macd', '<', 0)
                      ->orWhere('stock_indicators.macd_hist', '<', DB::raw('stock_prev_indicators.macd_hist'))
                      ->where('stock_indicators.obv', '<', DB::raw('stock_prev_indicators.obv'));
                break;
        }

        /*
         * Advanced Fundamental Screeners
         */
        switch ($advanceFundamental) {
            case 'earnings_quality_stability':
                $query->where('latest_stock_company_earnings_quality_score.score', '>=', 65)
                      ->where('stock_basic_financials_metric.netMargin', '>=', 0.15)
                      ->where(function ($q) {
                          $q->where('stock_basic_financials_metric.currentdividendyieldttm', '=', 0)
                            ->orWhere('stock_basic_financials_metric.currentdividendyieldttm', '>=', 0.02);
                      });
                break;
            case 'growth_accelerator_momentum':
                $query->where('stock_basic_financials_metric.epsGrowthTTMYoy', '>=', 0.15)
                      ->where('stock_price_metrics.data_ytd_price_return', '>=', 10)
                      ->where('stock_indicators.rsi', '<', 70)
                      ->whereColumn('latest_stock_recommendation_trends.strongBuy', '>', 'latest_stock_recommendation_trends.sell');
                break;
            case 'steady_income_plus':
                $query->where('stock_dividend_quarterly.avg_dividend', '>=', 0.5)
                      ->where(function ($q) {
                          $q->where('stock_basic_financials_metric.currentdividendyieldttm', '>=', 0.03)
                            ->orWhereNull('stock_basic_financials_metric.currentdividendyieldttm');
                      });
                break;
            case 'bullish_signal_finder':
                $query->where('stock_price_metrics.data_50_day_sma', '>', DB::raw('stock_price_metrics.data_100_day_sma'))
                      ->where('stock_indicators.rsi', '>=', 40)
                      ->where('stock_indicators.rsi', '<', 70)
                      ->where('stock_news_sentiments.sentiment_bullish', '>', 0.7);
                break;
            case 'bearish_warning_system':
                $query->where('stock_indicators.rsi', '>', 70)
                      ->where('latest_insider.trans_val', '<', 0)
                      ->where('stock_news_sentiments.sentiment_bearish', '>', 0.5);
                break;
            case 'insider_optimism_filter':
                $query->where('latest_insider.trans_val', '>', 0)
                      ->where('latest_institutional_ownership.value', '>=', 5);
                break;
            case 'institutional_magnet':
                $query->where('latest_institutional_ownership.percentage', '>=', 10);
                break;
            case 'sector_leader_tracker':
                // Pending implementation.
                break;
            case 'value_rebound':
                $query->where('stock_price_metrics.data_ytd_price_return', '<=', DB::raw('stock_price_metrics.data_52_week_low * 1.05'))
                      ->whereRaw('stock_quote.current_price / stock_basic_financials_metric.pettm < ?', [15])
                      ->where('stock_indicators.rsi', '<', 30);
                break;
            case 'economic_catalyst':
                $query->where('stock_news_sentiments.companynews_score', '>', 0.8);
                break;
        }

        // Apply Stock Sector filter if provided
        if (!empty($stockSector)) {
            $formattedSector = Str::of($stockSector)->replace('_', ' ')->title()->toString();
            $query->where('stock_symbol_info.sector', $formattedSector);
        }

        // Apply Market Cap filter
        if (!empty($marketCap) && $marketCap !== 'any') {
            switch ($marketCap) {
                case 'small':
                    $query->whereBetween('stock_symbol_info.market_cap', [30000000, 2000000000]);
                    break;
                case 'mid':
                    $query->whereBetween('stock_symbol_info.market_cap', [2000000000, 10000000000]);
                    break;
                case 'large':
                    $query->whereBetween('stock_symbol_info.market_cap', [10000000000, 200000000000]);
                    break;
                case 'micro':
                    $query->whereBetween('stock_symbol_info.market_cap', [300000000, 50000000]);
                    break;
                case 'mega':
                    $query->where('stock_symbol_info.market_cap', '>', 200000000000);
                    break;
                case 'nano':
                    $query->where('stock_symbol_info.market_cap', '<', 50000000);
                    break;
            }
        }

        // Apply Price filter
        if ($price !== 'any') {
            switch ($price) {
                case 'under_1':
                    $query->where('latest_candle.close_price', '<', 1);
                    break;
                case 'under_2':
                    $query->where('latest_candle.close_price', '<', 2);
                    break;
                case 'under_3':
                    $query->where('latest_candle.close_price', '<', 3);
                    break;
                case 'under_5':
                    $query->where('latest_candle.close_price', '<', 5);
                    break;
                case 'under_7':
                    $query->where('latest_candle.close_price', '<', 7);
                    break;
                case 'under_10':
                    $query->where('latest_candle.close_price', '<', 10);
                    break;
                case 'under_15':
                    $query->where('latest_candle.close_price', '<', 15);
                    break;
                case 'under_20':
                    $query->where('latest_candle.close_price', '<', 20);
                    break;
                case 'under_30':
                    $query->where('latest_candle.close_price', '<', 30);
                    break;
                case 'under_50':
                    $query->where('latest_candle.close_price', '<', 50);
                    break;
                case 'under_100':
                    $query->where('latest_candle.close_price', '<', 100);
                    break;
                case 'over_10':
                    $query->where('latest_candle.close_price', '>', 10);
                    break;
                case 'over_20':
                    $query->where('latest_candle.close_price', '>', 20);
                    break;
                case 'over_30':
                    $query->where('latest_candle.close_price', '>', 30);
                    break;
                case 'over_50':
                    $query->where('latest_candle.close_price', '>', 50);
                    break;
                case 'over_70':
                    $query->where('latest_candle.close_price', '>', 70);
                    break;
                case 'over_100':
                    $query->where('latest_candle.close_price', '>', 100);
                    break;
                case 'over_500':
                    $query->where('latest_candle.close_price', '>', 500);
                    break;
                case '1_to_5':
                    $query->whereBetween('latest_candle.close_price', [1, 5]);
                    break;
                case '1_to_10':
                    $query->whereBetween('latest_candle.close_price', [1, 10]);
                    break;
                case '1_to_20':
                    $query->whereBetween('latest_candle.close_price', [1, 20]);
                    break;
                case '5_to_10':
                    $query->whereBetween('latest_candle.close_price', [5, 10]);
                    break;
                case '10_to_20':
                    $query->whereBetween('latest_candle.close_price', [10, 20]);
                    break;
                case '10_to_50':
                    $query->whereBetween('latest_candle.close_price', [10, 50]);
                    break;
                case '20_to_50':
                    $query->whereBetween('latest_candle.close_price', [20, 50]);
                    break;
                case '50_to_100':
                    $query->whereBetween('latest_candle.close_price', [50, 100]);
                    break;
                case '100_to_500':
                    $query->whereBetween('latest_candle.close_price', [100, 500]);
                    break;
            }
        }

        // Apply Performance filter based on the specified period (daily, weekly, monthly)
        if (strpos($performance, 'daily') !== false) {
            switch ($performance) {
                case 'daily_5':
                    $query->where('latest_percentage.percentage', '>', 5);
                    break;
                case 'daily_down_5':
                    $query->where('latest_percentage.percentage', '<', -5);
                    break;
                case 'daily_10':
                    $query->where('latest_percentage.percentage', '>', 10);
                    break;
                case 'daily_down_10':
                    $query->where('latest_percentage.percentage', '<', -10);
                    break;
                case 'daily_15':
                    $query->where('latest_percentage.percentage', '>', 15);
                    break;
                case 'daily_down_15':
                    $query->where('latest_percentage.percentage', '<', -15);
                    break;
                default:
                    $query->where('latest_percentage.percentage', '>', 5);
                    break;
            }
        } elseif (strpos($performance, 'weekly') !== false) {
            switch ($performance) {
                case 'weekly_5':
                    $query->where('latest_percentage.percentage', '>', 5);
                    break;
                case 'weekly_down_5':
                    $query->where('latest_percentage.percentage', '<', -5);
                    break;
                case 'weekly_10':
                    $query->where('latest_percentage.percentage', '>', 10);
                    break;
                case 'weekly_down_10':
                    $query->where('latest_percentage.percentage', '<', -10);
                    break;
                case 'weekly_15':
                    $query->where('latest_percentage.percentage', '>', 15);
                    break;
                case 'weekly_down_15':
                    $query->where('latest_percentage.percentage', '<', -15);
                    break;
                default:
                    $query->where('latest_percentage.percentage', '>', 5);
                    break;
            }
        } elseif (strpos($performance, 'monthly') !== false) {
            switch ($performance) {
                case 'monthly_5':
                    $query->where('latest_percentage.percentage', '>', 5);
                    break;
                case 'monthly_down_5':
                    $query->where('latest_percentage.percentage', '<', -5);
                    break;
                case 'monthly_10':
                    $query->where('latest_percentage.percentage', '>', 10);
                    break;
                case 'monthly_down_10':
                    $query->where('latest_percentage.percentage', '<', -10);
                    break;
                case 'monthly_15':
                    $query->where('latest_percentage.percentage', '>', 15);
                    break;
                case 'monthly_down_15':
                    $query->where('latest_percentage.percentage', '<', -15);
                    break;
                default:
                    $query->where('latest_percentage.percentage', '>', 5);
                    break;
            }
        }

        // Generate a unique cache key based on filter parameters
        $cacheKey = 'stocks_result2_' . md5(json_encode([
            'ssector'           => $stockSector,
            'basic_fundamental' => $basicFundamental,
            'basic_technical'   => $basicTechnical,
            'advance_technical' => $advanceTechnical,
            'advance_fundamental' => $advanceFundamental,
            'market_cap'        => $marketCap,
            'price'             => $price,
            'performance'       => $performance,
            'page'              => $page,
            'per_page'          => $perPage,
        ]));

        // Retrieve results from cache or execute query if not cached
        $results = Cache::remember($cacheKey, 350, function () use ($query, $perPage, $offset) {
            return $query->where('stock_symbols.priority', '=', 1)
                         ->orderBy(DB::raw('stock_symbols.symbol'), 'ASC')
                         ->limit($perPage)
                         ->offset($offset)
                         ->get();
        });

        // Return the results as a JSON response
        return response()->json($results);
    }
}
