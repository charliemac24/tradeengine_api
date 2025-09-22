<?php



namespace App\Http\Controllers\endpoints\v1;



use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Str;



class StockScreenerController extends Controller

{

    /**

     * Retrieve all data from the front-end application, process, and send the result in JSON format.

     *

     * @param Request $request

     * @return \Illuminate\Http\JsonResponse

     */

    public function getScreenerResult(Request $request)

    {



        DB::enableQueryLog(); // Enable query log

        // Get the data from the front-end application

        $basic_fundamental = $request->input('basic_fundamental','');

        $basic_technical = $request->input('basic_technical','');

        $advance_technical = $request->input('advance_technical','');

        $advance_fundamental = $request->input('advance_fundamental','');

        $stock_sector = $request->input('ssector','');

        $market_cap = $request->input('market_cap','');

        $price = $request->input('price','');

        $performance = $request->input('performance','');



        // Page, per_page, and offset

        $page = $request->input('page', 1);

        $per_page = $request->input('per_page', 20);

        $offset = ($page - 1) * $per_page;







        // Build the query to join stock_symbols, stock_symbol_info, and stock_indicators tables, and others

        $candle_table = '';

        $percentage_table = '';

        if (strpos($performance, 'daily') !== false) {

            $candle_table = 'stock_candle_daily';

            $percentage_table = 'stock_percentage_daily';

        } elseif (strpos($performance, 'weekly') !== false) {

            $candle_table = 'stock_candle_weekly';

            $percentage_table = 'stock_percentage_weekly';

        } elseif (strpos($performance, 'monthly') !== false) {

            $candle_table = 'stock_candle_monthly';

            $percentage_table = 'stock_percentage_monthly';

        } else {

            $candle_table = 'stock_candle_daily';

            $percentage_table = 'stock_percentage_daily';

        }



// Build the query

$query = DB::table('stock_symbols')

    ->distinct()

    ->join('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')

    ->join('stocks_by_market_cap','stocks_by_market_cap.symbol','=','stock_symbols.symbol')

    ->leftJoin('stock_quote','stock_quote.stock_id','=','stock_symbols.id')
    

    //->join('stock_prev_indicators', 'stock_symbols.id', '=', 'stock_prev_indicators.stock_id')

    ->leftJoin('stock_dividend_quarterly','stock_symbols.id','=','stock_dividend_quarterly.stock_id')

    ->leftJoin('stock_indicators', 'stock_symbols.id', '=', 'stock_indicators.stock_id')

    //->leftJoin('latest_insider', 'stock_symbols.id', '=', 'latest_insider.stock_id')

    ->leftJoin(DB::raw("(SELECT stock_id, trans_val 

    FROM stock_insiders 

    WHERE (stock_id, trans_date) IN 

          (SELECT stock_id, MAX(trans_date) 

           FROM stock_insiders

           GROUP BY stock_id)

   ) as latest_insider"), 

'stock_symbols.id', '=', 'latest_insider.stock_id')

    ->leftJoin('stock_news_sentiments', 'stock_symbols.id', '=', 'stock_news_sentiments.stock_id')

    ->leftJoin(DB::raw("(SELECT stock_id, close_price,volume 

    FROM $candle_table 

    WHERE (stock_id, ts) IN 

          (SELECT stock_id, MAX(ts) 

           FROM $candle_table 

           GROUP BY stock_id)

   ) as latest_candle"), 

'stock_symbols.id', '=', 'latest_candle.stock_id')

    ->leftJoin(DB::raw("(SELECT stock_id,percentage FROM $percentage_table WHERE (stock_id, closing_date) IN 

    (SELECT stock_id, MAX(closing_date) FROM $percentage_table GROUP BY stock_id)

) as latest_percentage"), 'stock_symbols.id', '=', 'latest_percentage.stock_id')

    ->leftJoin('stock_basic_financials_metric', 'stock_symbols.id', '=', 'stock_basic_financials_metric.stock_id')

    ->leftJoin('stock_prev_indicators', 'stock_symbols.id', '=', 'stock_prev_indicators.stock_id')

    //->leftJoin('latest_stock_institutional_ownership', 'stock_symbols.id', '=', 'latest_stock_institutional_ownership.stock_id')

    ->leftJoin(DB::raw("(SELECT stock_id, value,percentage 

    FROM stock_institutional_ownership

    WHERE (stock_id, id) IN 

          (SELECT stock_id, MAX(id) 

           FROM stock_institutional_ownership 

           GROUP BY stock_id)

   ) as latest_latest_stock_institutional_ownership"), 

'stock_symbols.id', '=', 'latest_latest_stock_institutional_ownership.stock_id')

    ->leftJoin('stock_price_metrics', 'stock_symbols.id', '=', 'stock_price_metrics.stock_id')

    ->leftJoin('stock_price_target', 'stock_symbols.id', '=', 'stock_price_target.stock_id')

    //->leftJoin('latest_stock_recommendation_trends', 'stock_symbols.id', '=', 'latest_stock_recommendation_trends.stock_id')

    ->leftJoin(DB::raw("(SELECT stock_id, strongBuy,sell 

    FROM stock_recommendation_trends 

    WHERE (stock_id, id) IN 

          (SELECT stock_id, MAX(id) 

           FROM stock_recommendation_trends 

           GROUP BY stock_id)

   ) as latest_stock_recommendation_trends"), 

'stock_symbols.id', '=', 'latest_stock_recommendation_trends.stock_id')

//->leftJoin('stock_economic_calendar','stock_symbols.id','=','stock_economic_calendar.stock_id')

    //->leftJoin('latest_stock_company_earnings_quality_score', 'stock_symbols.id', '=', 'latest_stock_company_earnings_quality_score.stock_id')

    ->leftJoin(DB::raw("(SELECT stock_id, score 

    FROM stock_company_earnings_quality_score 

    WHERE (stock_id, period) IN 

          (SELECT stock_id, MAX(period) 

           FROM stock_company_earnings_quality_score

           GROUP BY stock_id)

   ) as latest_stock_company_earnings_quality_score"), 

'stock_symbols.id', '=', 'latest_stock_company_earnings_quality_score.stock_id')

    ->select('stock_symbols.id','stock_symbols.symbol','stock_quote.current_price', 'stock_symbol_info.sector','latest_latest_stock_institutional_ownership.value','latest_latest_stock_institutional_ownership.percentage','stock_symbol_info.industry','stock_symbol_info.market_cap','stock_symbol_info.company_name','latest_candle.close_price AS price','latest_percentage.percentage AS change','latest_candle.volume') // Selecting only required fields

    ->where('stock_symbol_info.company_name', '!=', '')

    //->where('latest_candle.volume', '>', 0)

    ->where('stock_symbol_info.sector', '!=', '')

    ->where('stocks_by_market_cap.processed', '=', 1)

    ->where('stock_symbol_info.industry', '!=', '');    

        // Default condition

        //$query->where('latest_insider.trans_date', '>=', date('Y-m-d', strtotime('-90 days'))); 

        

        // Basic fundamental filters

        if ( $basic_fundamental == 'bargain_bin_finder' ) {

            $query->where('stock_basic_financials_metric.pettm', '<', 15)

                  ->where('stock_basic_financials_metric.pbannual', '<', 1.5)

                  ->where('stock_basic_financials_metric.psttm', '<', 2)

                  ->where('stock_basic_financials_metric.pfcfsharettm', '<', 10);

        } elseif ( $basic_fundamental == 'power_profit_picks' ) {

            $query->where('stock_basic_financials_metric.roettm', '>=', 15)

                  ->where('stock_basic_financials_metric.roattm', '>=', 8)

                  ->where('stock_basic_financials_metric.operatingmarginttm', '>=', 12)

                  ->where('stock_basic_financials_metric.netprofitmarginttm', '>=', 10);

        } elseif ( $basic_fundamental == 'rising_stars_tracker' ){

            $query->where('stock_basic_financials_metric.epsgrowth5y', '>=', 10)

                  ->where('stock_basic_financials_metric.revenuegrowth5y', '>=', 10)

                  ->where('stock_basic_financials_metric.bookvaluesharegrowth5y', '>=', 5);

        } elseif ( $basic_fundamental == 'dividend_dollar_hunter' ) {

            $query->where('stock_basic_financials_metric.currentdividendyieldttm', '>=', 3)

                  ->where('stock_basic_financials_metric.dividendgrowthrate5y', '>=', 5)

                  ->where('stock_basic_financials_metric.payoutratiottm', '<=', 70);

        } elseif ( $basic_fundamental == 'smart_combo_stock_picker' ) {

            $query->where( 'stock_basic_financials_metric.pettm','<',15 )

                  ->where( 'stock_basic_financials_metric.pbannual','<',1.5 )

                  ->where( 'stock_basic_financials_metric.roettm','>=',15 )

                  ->where( 'stock_basic_financials_metric.netprofitmarginttm','>=',10 );

        } elseif ( $basic_fundamental == 'make_it_double' ) {

            $query->where( 'stock_basic_financials_metric.epsgrowth5y','>=',10 )

                  ->where( 'stock_basic_financials_metric.revenuegrowth5y','>=',10 )

                  ->where( 'stock_basic_financials_metric.dividendgrowthrate5y','>=',5 )

                  ->where( 'stock_basic_financials_metric.payoutratiottm','<=',70 )

                  ->where( 'stock_basic_financials_metric.currentdividendyieldttm','>=',3 );

        } elseif ( $basic_fundamental == 'stocks_built_like_tanks' ) {

            $query->where( 'stock_basic_financials_metric.currentratioannual','>=',1.5 )

                  ->where( 'stock_basic_financials_metric.quickratioannual','>=',1 )

                  ->where( 'stock_basic_financials_metric.totaldebt_totalequityannual','<',1 )

                  ->where( 'stock_basic_financials_metric.cashflowpersharettm','>',0 );

        }



        // Basic technical screeners

        if ( $basic_technical == 'bullish_momentum_reversal' ){

            $query->where('stock_prev_indicators.rsi', '<', 30)

                  ->where('stock_indicators.rsi', '>', 30)

                  ->where('stock_prev_indicators.price', '<', 'stock_prev_indicators.ema_10')

                  ->where('stock_indicators.price', '>', 'stock_indicators.ema_10');

        } elseif ( $basic_technical == 'strong_trend_continuation' ){

            $query->where('stock_indicators.price', '>', 'stock_indicators.sma_50')

                  ->where('stock_prev_indicators.macd_hist', '<', 0)

                  ->where('stock_indicators.macd_hist', '>', 0);

        } elseif ( $basic_technical == 'bearish_reversal_alert' ){

            $query->where('stock_prev_indicators.rsi', '>', 70)

                  ->where('stock_indicators.rsi', '<', 70)

                  ->where('stock_prev_indicators.price', '>', 'stock_prev_indicators.sma_50')

                  ->where('stock_indicators.price', '<', 'stock_indicators.sma_50');

        } elseif ( $basic_technical == 'golden_cross_momentum_confirmation' ){

            $query->where('stock_prev_indicators.rsi', '<','stock_prev_indicators.sma_50')

                  ->where('stock_indicators.sma_50', '>', 'stock_indicators.sma_200')

                  ->where('stock_indicators.rsi', '>', 55);

        } elseif ( $basic_technical == 'breakout_watch' ){

            $query->where('stock_indicators.price', '>', 'stock_basic_financials_metric.fifty_two_week_high')

                  ->where('latest_candle.volume', '>', 'stock_basic_financials_metric.ten_day_ave_trading_vol');

        } elseif ( $basic_technical == 'bearish_macd_divergence' ){

            $query->where('stock_prev_indicators.macd', '>', 'stock_prev_indicators.macd_signal_line')

                  ->where('stock_indicators.macd', '<', 'stock_indicators.macd_signal_line')

                  ->where('stock_prev_indicators.price', '>', 'stock_prev_indicators.ema_20')

                  ->where('stock_indicators.price', '<', 'stock_indicators.ema_20');

        } elseif ( $basic_technical == 'bullish_bollinger_band_reversal' ){

            $query->where('stock_prev_indicators.price', '<', 'stock_prev_indicators.lower_b')

                  ->where('stock_indicators.price', '>', 'stock_indicators.lower_b')

                  ->where('stock_indicators.rsi', '<', 35);

        }



        // Advanced technical screener

        if( $advance_technical == 'bullish_cross_climb' ) {

            $query->where('stock_indicators.sma_20', '>', 'stock_indicators.sma_50')

                  ->where('stock_indicators.macd', '>', 0)

                  ->where('stock_indicators.macd_hist', '>', 0)

                  ->where('stock_indicators.rsi', '<', 70);

        } elseif ( $advance_technical == 'bearish_cross_climb' ) {

            $query->where('stock_indicators.sma_20', '<', 'stock_indicators.sma_50')

                  ->where('stock_indicators.macd', '<', 0)

                  ->where('stock_indicators.macd_hist', '<', 0)

                  ->where('stock_indicators.rsi', '>', 70);

        } elseif ( $advance_technical == 'bullish_bollinger_bounce' ){

            $query->where('stock_indicators.price', '<', 'stock_indicators.lower_b')

                  ->where('stock_indicators.adx', '<', 20)

                  ->where('stock_indicators.rsi', '<', 30);

        } elseif ( $advance_technical == 'bearish_bollinger_bounce' ){

            $query->where('stock_indicators.price', '<', 'stock_indicators.upperband')

                  ->where('stock_indicators.adx', '<', 20)

                  ->where('stock_indicators.rsi', '>', 70);

        } elseif ( $advance_technical == 'bullish_swingsurge' ){

            $query->where('stock_indicators.slowk', '>', 'stock_indicators.slowd')

                  ->where('stock_prev_indicators.slowk', '<', 20)

                  ->where('stock_indicators.slowk', '>', 20)

                  ->where('stock_indicators.rsi', '>', 50)

                  ->where('stock_prev_indicators.rsi', '<', 50)

                  ->where('stock_indicators.aroon_up', '>', 70)

                  ->where('stock_indicators.aroon_down', '<', 30);

        } elseif ( $advance_technical == 'bearish_swingsurge' ){

            $query->where('stock_indicators.slowk', '<', 'stock_indicators.slowd')

                  ->where('stock_prev_indicators.slowk', '>', 80)

                  ->where('stock_indicators.slowk', '<', 80)

                  ->where('stock_indicators.rsi', '<', 50)

                  ->where('stock_prev_indicators.rsi', '>', 50)

                  ->where('stock_indicators.aroon_down', '>', 70)

                  ->where('stock_indicators.aroon_up', '<', 30);    

        } elseif ( $advance_technical == 'bullish_trend_tracker' ){

            $query->where('stock_indicators.cci', '>', -100)

                  ->where('stock_prev_indicators.sar', '>', 'stock_prev_indicators.price')

                  ->where('stock_indicators.sar', '<', 'stock_indicators.price')

                  ->where('stock_indicators.adx', '>', 20);

        } elseif ( $advance_technical == 'bearish_trend_tracker' ){

            $query->where('stock_indicators.cci', '<', 100)

                  ->where('stock_prev_indicators.sar', '<', 'stock_prev_indicators.price')

                  ->where('stock_indicators.sar', '>', 'stock_indicators.price')

                  ->where('stock_indicators.adx', '>', 20);

        } elseif ( $advance_technical == 'bullish_volume_vision' ){

            $query->where('stock_indicators.sma_10', '>', 'stock_indicators.sma_50')

                  ->where('stock_indicators.sma_50', '>', 'stock_indicators.sma_100')

                  ->where('stock_indicators.macd_hist', '>', 0)

                  ->orWhere('stock_indicators.macd_hist', '>', 'stock_prev_indicators.macd_hist')

                  ->where('stock_indicators.obv', '>', 'stock_prev_indicators.obv');

        } elseif ( $advance_technical == 'bearish_volume_vision' ){

            $query->where('stock_indicators.sma_10', '<', 'stock_indicators.sma_50')

                  ->where('stock_indicators.sma_50', '<', 'stock_indicators.sma_100')

                  ->where('stock_indicators.macd', '<', 0)

                  ->orWhere('stock_indicators.macd_hist', '<', 'stock_prev_indicators.macd_hist')

                  ->where('stock_indicators.obv', '<', 'stock_prev_indicators.obv');

        }



        // Advanced fundamental screener

        if($advance_fundamental == 'earnings_quality_stability'){

            // not yet implemented : DONE

            // earnings_quality : https://finnhub.io/docs/api/company-earnings-quality-score-api

            $query->where('latest_stock_company_earnings_quality_score.score', '>=', 65)

                  ->where('stock_basic_financials_metric.netMargin', '>=', 0.15)

                  ->where(function($query) {

                        $query->where('stock_basic_financials_metric.currentdividendyieldttm', '=', 0)

                                ->orWhere('stock_basic_financials_metric.currentdividendyieldttm', '>=', 0.02);

            });

        } elseif($advance_fundamental == 'growth_accelerator_momentum'){

            $query->where('stock_basic_financials_metric.epsGrowthTTMYoy', '>=', 0.15)

                  ->where('stock_price_metrics.data_ytd_price_return', '>=', 10)

                  ->where('stock_indicators.rsi', '<', 70)

                  ->whereColumn('latest_stock_recommendation_trends.strongBuy', '>', 'latest_stock_recommendation_trends.sell');

        } elseif($advance_fundamental == 'steady_income_plus'){

            $query->where('stock_dividend_quarterly.avg_dividend', '>=', 0.5)

            ->where(function($query) {

                $query->where('stock_basic_financials_metric.currentdividendyieldttm', '>=', 0.03)

                        ->orWhereNull('stock_basic_financials_metric.currentdividendyieldttm');

            });

        } elseif($advance_fundamental == 'bullish_signal_finder'){

            $query->where('stock_price_metrics.data_50_day_sma', '>', 'stock_price_metrics.data_100_day_sma')

                ->where('stock_indicators.rsi', '>=', 40)

                ->where('stock_indicators.rsi', '<', 70)

                ->where('stock_news_sentiments.sentiment_bullish', '>', 0.7);

        } elseif($advance_fundamental == 'bearish_warning_system'){

            $query->where('stock_indicators.rsi', '>', 70)

                 ->where('latest_insider.trans_val', '<', 0)

                 ->where('stock_news_sentiments.sentiment_bearish', '>', 0.5);

        } elseif($advance_fundamental == 'insider_optimism_filter'){

            $query->where('latest_insider.trans_val', '>', 0)

                  ->where('latest_latest_stock_institutional_ownership.value', '>=', 5);

                  //->whereRaw('stock_price_metrics.data_ytd_price_return', '>=', DB::raw('stock_price_metrics.data_52_week_low * 1.05'));

        } elseif($advance_fundamental == 'institutional_magnet'){

            // not yet implemented: DONE

            // inst_own : institutional ownership : percentage

            $query->where('latest_latest_stock_institutional_ownership.percentage', '>=', 10);

                  //->where('latest_stock_recommendation_trends.strongBuy', '>', 'latest_stock_recommendation_trends.sell')

                  //->where('stock_price_target.target_mean', '>', 'stock_price_metrics.data_ytd_price_return');

        } elseif($advance_fundamental == 'sector_leader_tracker'){

            // not yet implemented :  Jun will check it first he said

            // company_return : ytdReturn : https://finnhub.io/api/v1/stock/price-metric?symbol=AAPL&token=ctukd71r01qg98tdggqgctukd71r01qg98tdggr0

           // $query->whereColumn('stock_price_metrics.data_ytd_price_return', '>', 'stock_price_metrics.data_ytd_price_return')

            //        ->whereColumn('latest_stock_recommendation_trends.strongBuy', '>', 'latest_stock_recommendation_trends.sell');

        } elseif($advance_fundamental == 'value_rebound'){

            /**$query->whereRaw('stock_price_metrics.data_ytd_price_return', '<=', DB::raw('stock_price_metrics.data_52_week_low * 1.05'))

                    ->where('stock_quote.current_price /  stock_basic_financials_metric.pettm', '<', 15)

                    ->where('stock_indicators.rsi', '<', 30);**/

                    

            // Using where() properly to compare a column with a computed value:

            $query->where('stock_price_metrics.data_ytd_price_return', '<=', DB::raw('stock_price_metrics.data_52_week_low * 1.05'))

                  ->whereRaw('stock_quote.current_price / stock_basic_financials_metric.pettm < ?', [15])

                  ->where('stock_indicators.rsi', '<', 30);



        }elseif($advance_fundamental == 'economic_catalyst'){

            // not yet implemented

            // upcoming_event : high impact economic event

           // $query->where('stock_economic_calendar.impact', 'high')

          $query->where('stock_news_sentiments.companynews_score', '>', 0.8);

          //->where('stock_social_sentiments.score', '>', 0);

        }



        // Stock sector filters

        if ($stock_sector !== '') {

            $ssector = $stock_sector 

        ? Str::of($stock_sector)->replace('_', ' ')->title()->toString() 

        : null;

            $query->where('stock_symbol_info.sector', $ssector);

        }



        // Market Cap filters

        if (!empty($market_cap) && $market_cap !== 'any') {

            switch ($market_cap) {

                case 'small':

                    $query->whereBetween('stock_symbol_info.market_cap', [30000000, 2000000000]); // 30 million to 2 billion

                    break;

                case 'mid':

                    $query->whereBetween('stock_symbol_info.market_cap', [2000000000, 10000000000]); // 2 billion to 10 billion

                    break;

                case 'large':

                    $query->whereBetween('stock_symbol_info.market_cap', [10000000000, 200000000000]); // 10 billion to 200 billion

                    break;

                case 'micro':

                    $query->whereBetween('stock_symbol_info.market_cap', [300000000, 50000000]); // 30 million to 50 million

                    break;

                case 'mega':

                    $query->where('stock_symbol_info.market_cap', '>', 200000000000); // greater than 200 billion

                    break;

                case 'nano':

                    $query->where('stock_symbol_info.market_cap', '<', 50000000); // less than 50 million

                    break;

                default:

                    // Add other ranges as needed

                    break;

            }

        }



        // Price filters

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

                default:

                    // No filter for unrecognized values

                    break;

            }

        }



        // Performance filters

        if (strpos($performance, 'daily') !== false) {

            if ($performance === 'daily_5') {

                $query->where('latest_percentage.percentage', '>', 5);

            } elseif ($performance === 'daily_down_5') {

                $query->where('latest_percentage.percentage', '<', -5);                

            } elseif ($performance === 'daily_10') {

                $query->where('latest_percentage.percentage', '>', 10);

            } elseif ($performance === 'daily_down_10') {

                $query->where('latest_percentage.percentage', '<', -10);

            } elseif ($performance === 'daily_15') {

                $query->where('latest_percentage.percentage', '>', 15);

            } elseif ($performance === 'daily_down_15') {

                $query->where('latest_percentage.percentage', '<', -15);

            } else {

                $query->where('latest_percentage.percentage', '>', 5);

            }

        } elseif (strpos($performance, 'weekly') !== false) {

            if ($performance === 'weekly_5') {

                $query->where('latest_percentage.percentage', '>', 5);

            } elseif ($performance === 'weekly_down_5') {

                $query->where('latest_percentage.percentage', '<', -5);

            } elseif ($performance === 'weekly_10') {

                $query->where('latest_percentage.percentage', '>', 10);

            } elseif ($performance === 'weekly_down_10') {

                $query->where('latest_percentage.percentage', '<', -10);

            } elseif ($performance === 'weekly_15') {

                $query->where('latest_percentage.percentage', '>', 15);

            } elseif ($performance === 'weekly_down_15') {

                $query->where('latest_percentage.percentage', '<', -15);

            } else {

                $query->where('latest_percentage.percentage', '>', 5);

            }

        } elseif (strpos($performance, 'monthly') !== false) {

            if ($performance === 'monthly_5') {

                $query->where('latest_percentage.percentage', '>', 5);

            } elseif ($performance === 'monthly_down_5') {

                $query->where('latest_percentage.percentage', '<', -5);

            } elseif ($performance === 'monthly_10') {

                $query->where('latest_percentage.percentage', '>', 10);

            } elseif ($performance === 'monthly_down_10') {

                $query->where('latest_percentage.percentage', '<', -10);

            } elseif ($performance === 'monthly_15') {

                $query->where('latest_percentage.percentage', '>', 15);

            } elseif ($performance === 'monthly_down_15') {

                $query->where('latest_percentage.percentage', '<', -15);

            } else {

                $query->where('latest_percentage.percentage', '>', 5);

            }

        }





        $cacheKey = 'stocks_result2_'.md5(json_encode([

            'ssector'=> $stock_sector,

            'basic_fundamental' => $basic_fundamental,

            'basic_technical' => $basic_technical,

            'advance_technical' => $advance_technical,

            'advance_fundamental' => $advance_fundamental,

            'stock_sector' => $stock_sector,

            'market_cap' => $market_cap,

            'price' => $price,

            'performance' => $performance,

            'page' => $page,

            'per_page' => $per_page

        ]));



$results = Cache::remember($cacheKey, 350, function () use ($query, $per_page, $offset) {

    return $query->where('stock_symbols.priority','=',1)

                 ->orderBy(DB::raw('stock_symbols.symbol'), 'ASC')

                 ->limit($per_page)

                 ->offset($offset)

                 ->get();

});



        // Add ordering, limit, and offset for pagination

        /**$query->orderBy('stock_symbols.symbol', 'ASC')

             ->orderBy('stock_symbols.priority', 'DESC')

              ->limit($per_page)

              ->offset($offset);



        // Execute the query and get the results

        $results = $query->get();

        

        // Get the last query

$queries = DB::getQueryLog();

$lastQuery = end($queries);**/



// Display the query

//dd($lastQuery);



// Retrieve the log of executed queries

$queryLog = DB::getQueryLog();



// Get the last query from the log

$lastQuery = end($queryLog);



// Display the last query details

//dd($lastQuery);

        // Return the results in JSON format

        return response()->json($results);



    }

}

