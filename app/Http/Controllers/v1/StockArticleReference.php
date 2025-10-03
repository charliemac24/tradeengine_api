<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockArticleReference extends Controller
{
    public static function getAllReferences(Request $request): array
    {
        $symbols = $request->input('symbols');
        $from_date = $request->input('from_date');

        // Convert comma-separated string to an array
        $symbolsArray = array_map('trim', explode(',', $symbols));

        $currentMonth = date('Y-m-01'); // First day of the current month
        $currentYear = date('Y-01-01'); // First day of the current year

        $query = DB::table('stock_symbols')
            ->leftJoin('stock_company_news', 'stock_company_news.stock_id', '=', 'stock_symbols.id')
            ->leftJoin('stock_basic_financials_metric', DB::raw('BINARY stock_symbols.id'), '=', DB::raw('BINARY stock_basic_financials_metric.stock_id'))
            ->leftJoin('stock_price_target', DB::raw('BINARY stock_symbols.id'), '=', DB::raw('BINARY stock_price_target.stock_id'))
            ->leftJoin('stock_indicators', DB::raw('BINARY stock_symbols.id'), '=', DB::raw('BINARY stock_indicators.stock_id'))
            ->leftJoin('stock_trading_score', DB::raw('BINARY stock_trading_score.symbol'), '=', DB::raw('BINARY stock_symbols.symbol'))
            ->leftJoin('stock_symbol_info', DB::raw('BINARY stock_symbols.id'), '=', DB::raw('BINARY stock_symbol_info.stock_id'))
            ->leftJoin('stock_sector_metrics', DB::raw('BINARY stock_sector_metrics.sector'), '=', DB::raw('BINARY stock_symbol_info.sector'))
            ->leftJoin('stock_earnings_quality_quarterly', DB::raw('BINARY stock_earnings_quality_quarterly.stock_id'), '=', DB::raw('BINARY stock_symbol_info.stock_id'))
            ->leftJoin(
                DB::raw('(SELECT stock_id, SUM(buy) as total_buy, SUM(hold) as total_hold, SUM(sell) as total_sell, SUM(strongBuy) as total_strongBuy, SUM(strongSell) as total_strongSell 
                          FROM stock_recommendation_trends 
                          GROUP BY stock_id) as recommendation_totals'),
                DB::raw('BINARY stock_symbols.id'),
                '=',
                DB::raw('BINARY recommendation_totals.stock_id')
            )
            ->leftJoin(
                DB::raw('(SELECT stock_id, 
                                 MAX(close_price) as current_price, 
                                 MIN(CASE WHEN ts >= "' . $currentMonth . '" THEN close_price END) as first_price_of_month, 
                                 MIN(CASE WHEN ts >= "' . $currentYear . '" THEN close_price END) as first_price_of_year 
                          FROM stock_candle_daily 
                          GROUP BY stock_id) as candle_data'),
                DB::raw('BINARY stock_symbols.id'),
                '=',
                DB::raw('BINARY candle_data.stock_id')
            )
            ->whereIn(DB::raw('BINARY stock_symbols.symbol'), $symbolsArray);

        if ($from_date) {
            $query->where(DB::raw('BINARY stock_company_news.date_time'), '>=', $from_date);
        }

        $results = $query->select(
                'stock_symbols.symbol',
                'stock_indicators.rsi',
                'stock_indicators.ema_50',
                'stock_indicators.sma_50',
                'stock_basic_financials_metric.pettm',
                'stock_basic_financials_metric.fifty_two_week_low',
                'stock_basic_financials_metric.fifty_two_week_high',
                'stock_basic_financials_metric.netmargin',
                'stock_price_target.number_analysts',
                'stock_price_target.target_high',
                'stock_price_target.target_low',
                'stock_price_target.target_mean',
                'stock_price_target.target_median',
                'stock_trading_score.technical_score',
                'stock_trading_score.fundamental_score',
                'stock_trading_score.news_sentiment_score',
                'stock_trading_score.analyst_score',
                'stock_trading_score.trade_engine_score',
                'stock_earnings_quality_quarterly.capitalAllocation',
                'stock_earnings_quality_quarterly.growth',
                'stock_earnings_quality_quarterly.letterScore',
                'stock_earnings_quality_quarterly.leverage',
                'stock_earnings_quality_quarterly.profitability',
                'stock_earnings_quality_quarterly.score',
                'recommendation_totals.total_buy',
                'recommendation_totals.total_hold',
                'recommendation_totals.total_sell',
                'recommendation_totals.total_strongBuy',
                'recommendation_totals.total_strongSell',
                'candle_data.current_price',
                'candle_data.first_price_of_month',
                'candle_data.first_price_of_year',
                DB::raw('(candle_data.current_price - candle_data.first_price_of_month) as price_difference_month'),
                DB::raw('(candle_data.current_price - candle_data.first_price_of_year) as price_difference_year'),
                DB::raw('JSON_ARRAYAGG(
                JSON_OBJECT(
                "headline", stock_company_news.headline, 
                "date_time", stock_company_news.date_time, 
                "summary", stock_company_news.summary
                )) as news')
            )
            ->groupBy(
                'stock_symbols.symbol',
                'stock_indicators.rsi',
                'stock_indicators.ema_50',
                'stock_indicators.sma_50',
                'stock_basic_financials_metric.pettm',
                'stock_basic_financials_metric.fifty_two_week_low',
                'stock_basic_financials_metric.fifty_two_week_high',
                'stock_basic_financials_metric.netmargin',
                'stock_price_target.number_analysts',
                'stock_price_target.target_high',
                'stock_price_target.target_low',
                'stock_price_target.target_mean',
                'stock_price_target.target_median',
                'stock_trading_score.technical_score',
                'stock_trading_score.fundamental_score',
                'stock_trading_score.news_sentiment_score',
                'stock_trading_score.analyst_score',
                'stock_trading_score.trade_engine_score',
                'stock_earnings_quality_quarterly.capitalAllocation',
                'stock_earnings_quality_quarterly.growth',
                'stock_earnings_quality_quarterly.letterScore',
                'stock_earnings_quality_quarterly.leverage',
                'stock_earnings_quality_quarterly.profitability',
                'stock_earnings_quality_quarterly.score',
                'recommendation_totals.total_buy',
                'recommendation_totals.total_hold',
                'recommendation_totals.total_sell',
                'recommendation_totals.total_strongBuy',
                'recommendation_totals.total_strongSell',
                'candle_data.current_price',
                'candle_data.first_price_of_month',
                'candle_data.first_price_of_year'
            )
            ->get()
            ->toArray();

        return $results;
    }


    public static function getAllReferencesV2(Request $request): array
    {
        $symbols     = $request->input('symbols');
        $from_date   = $request->input('from_date');
        $symbolsArray = array_map('trim', explode(',', $symbols));

        $currentMonth = date('Y-01-01') === date('Y-m-01') ? date('Y-m-01') : date('Y-m-01');
        $currentYear  = date('Y-01-01');
        $currentDate  = date('Y-m-d');
        $futureDate   = date('Y-m-d', strtotime('+7 days'));

        $query = DB::table('stock_symbols')
            // basic joins
            ->leftJoin('stock_company_news', 'stock_company_news.stock_id', '=', 'stock_symbols.id')
            ->leftJoin('stock_basic_financials_metric', 'stock_basic_financials_metric.stock_id', '=', 'stock_symbols.id')
            ->leftJoin('stock_price_target',        'stock_price_target.stock_id',        '=', 'stock_symbols.id')
            ->leftJoin('stock_indicators',          'stock_indicators.stock_id',          '=', 'stock_symbols.id')
            ->leftJoin('stock_trading_score', DB::raw('BINARY stock_trading_score.symbol'), '=', DB::raw('BINARY stock_symbols.symbol'))
            ->leftJoin('stock_symbol_info', DB::raw('BINARY stock_symbols.id'), '=', DB::raw('BINARY stock_symbol_info.stock_id'))
            // ← NEW: earnings quality quarterly
            ->leftJoin('stock_earnings_quality_quarterly',
                    'stock_earnings_quality_quarterly.stock_id',
                    '=',
                    'stock_symbols.id')
            ->leftJoin('stock_sector_metrics', DB::raw('BINARY stock_sector_metrics.sector'), '=', DB::raw('BINARY stock_symbol_info.sector'))
            // earnings calendar (latest per symbol)
            ->leftJoin(
                DB::raw('(SELECT
                            symbol COLLATE utf8mb4_general_ci AS symbol,
                            cal_date AS future_calendar_date,
                            revenue_estimate,
                            revenue_actual
                        FROM stock_earnings_calendar
                        WHERE cal_date = (
                            SELECT MAX(cal_date)
                            FROM stock_earnings_calendar AS sub
                            WHERE sub.symbol = stock_earnings_calendar.symbol
                        )
                        ) AS earnings_data'),
                DB::raw('earnings_data.symbol'),
                '=',
                DB::raw('stock_symbols.symbol COLLATE utf8mb4_general_ci')
            )

            // recommendation trends aggregated
            ->leftJoin(
                DB::raw('(SELECT
                            stock_id,
                            SUM(buy)       AS total_buy,
                            SUM(hold)      AS total_hold,
                            SUM(sell)      AS total_sell,
                            SUM(strongBuy) AS total_strongBuy,
                            SUM(strongSell)AS total_strongSell
                        FROM stock_recommendation_trends
                        GROUP BY stock_id
                        ) AS recommendation_totals'),
                'recommendation_totals.stock_id',
                '=',
                'stock_symbols.id'
            )

            // candle data for prices
            ->leftJoin(
                DB::raw('(SELECT 
                            stock_id, 
                            close_price, 
                            first_price_of_month, 
                            first_price_of_year
                      FROM (
                          SELECT 
                              stock_id, 
                              close_price, 
                              MIN(CASE WHEN ts = "' . date('Y-m-01') . '" OR ts = "'.date('Y-m-02').'" OR ts = "'.date('Y-m-03').'" THEN close_price END) OVER (PARTITION BY stock_id) AS first_price_of_month,
                              MIN(CASE WHEN ts = "' . date('Y-01-01') . '" OR ts = "'.date('Y-01-02').'" THEN close_price END) OVER (PARTITION BY stock_id) AS first_price_of_year,
                              ROW_NUMBER() OVER (PARTITION BY stock_id ORDER BY id DESC) AS row_num
                          FROM stock_candle_daily
                      ) ranked
                      WHERE row_num = 1
                     ) AS candle_data'),
                'candle_data.stock_id',
                '=',
                'stock_symbols.id'
            )
            ->join('stocks_by_market_cap', 'stocks_by_market_cap.symbol', '=', 'stock_symbols.symbol')
            //->where('stocks_by_market_cap.processed', '=', 1)
           
            // filter symbols
            ->whereIn(DB::raw('stock_symbols.symbol COLLATE utf8mb4_general_ci'), $symbolsArray);

        if ($from_date) {
            $query->where(
                DB::raw('stock_company_news.date_time COLLATE utf8mb4_general_ci'),
                '>=',
                $from_date
            );
        }

        $results = $query->select(
                // core symbol + indicators
                'stock_symbols.symbol',
                'stock_indicators.rsi',
                'stock_indicators.ema_50',
                'stock_indicators.sma_50',

                'stock_symbol_info.sector',

                // basic financials
                'stock_basic_financials_metric.pettm',
                'stock_basic_financials_metric.fifty_two_week_low',
                'stock_basic_financials_metric.fifty_two_week_high',
                'stock_basic_financials_metric.netmargin',

                // trading scores
                'stock_trading_score.technical_score',
                'stock_trading_score.fundamental_score',
                'stock_trading_score.news_sentiment_score',
                'stock_trading_score.analyst_score',
                'stock_trading_score.trade_engine_score',


                // price targets
                'stock_price_target.number_analysts',
                'stock_price_target.target_high',
                'stock_price_target.target_low',
                'stock_price_target.target_mean',
                'stock_price_target.target_median',

                // ← earnings quality quarterly with aliases
                'stock_earnings_quality_quarterly.capitalAllocation',
                'stock_earnings_quality_quarterly.growth',
                'stock_earnings_quality_quarterly.letterScore',
                'stock_earnings_quality_quarterly.leverage',
                'stock_earnings_quality_quarterly.profitability',
                'stock_earnings_quality_quarterly.score AS earnings_quality_score',

                // recommendations
                'recommendation_totals.total_buy',
                'recommendation_totals.total_hold',
                'recommendation_totals.total_sell',
                'recommendation_totals.total_strongBuy',
                'recommendation_totals.total_strongSell',

                // sector metrics
                'stock_sector_metrics.peTTM AS sector_peTTM',
                'stock_sector_metrics.revenueGrowthQuarterlyYoy AS sector_revenueGrowthQuarterlyYoy',
                'stock_sector_metrics.payoutRatioTTM AS sector_payoutRatioTTM',

                // candle data + diffs
                'candle_data.close_price',
                'candle_data.first_price_of_month',
                'candle_data.first_price_of_year',
                DB::raw('(candle_data.close_price - candle_data.first_price_of_month) as price_difference_month'),
                DB::raw('(candle_data.close_price - candle_data.first_price_of_year)  as price_difference_year'),

                // upcoming earnings flag + estimate
                DB::raw('CASE
                            WHEN earnings_data.future_calendar_date BETWEEN "' . $currentDate . '" AND "' . $futureDate . '"
                            THEN "yes" ELSE "no"
                        END AS has_earnings_within_7_days'),

                // past earnings flag + actual revenue
                DB::raw('CASE
                            WHEN earnings_data.future_calendar_date BETWEEN DATE_SUB("' . $currentDate . '", INTERVAL 7 DAY) AND "' . $currentDate . '"
                            THEN "yes" ELSE "no"
                        END AS had_earnings_within_7_days'),

                // past cal_date when revenue_actual is not null
                DB::raw('CASE
                            WHEN earnings_data.revenue_actual IS NOT NULL
                            THEN earnings_data.future_calendar_date
                            ELSE NULL
                        END AS past_calendar_date'),
                'earnings_data.revenue_actual',
                'earnings_data.future_calendar_date',
                'earnings_data.revenue_estimate',              
                

                // company news aggregated into JSON
                DB::raw('JSON_ARRAYAGG(
                            JSON_OBJECT(
                            "headline",  stock_company_news.headline,
                            "date_time", stock_company_news.date_time,
                            "summary",   stock_company_news.summary
                            )
                        ) AS news')
            )
            ->groupBy(
                'stock_symbols.symbol',
                'stock_indicators.rsi',
                'stock_indicators.ema_50',
                'stock_indicators.sma_50',
                'stock_basic_financials_metric.pettm',
                'stock_basic_financials_metric.fifty_two_week_low',
                'stock_basic_financials_metric.fifty_two_week_high',
                'stock_basic_financials_metric.netmargin',
                'stock_price_target.number_analysts',
                'stock_price_target.target_high',
                'stock_price_target.target_low',
                'stock_price_target.target_mean',
                'stock_price_target.target_median',

                'stock_symbol_info.sector',

                'stock_trading_score.technical_score',
                'stock_trading_score.fundamental_score',
                'stock_trading_score.news_sentiment_score',
                'stock_trading_score.analyst_score',
                'stock_trading_score.trade_engine_score',

                // match your selects for groupBy
                'stock_earnings_quality_quarterly.capitalAllocation',
                'stock_earnings_quality_quarterly.growth',
                'stock_earnings_quality_quarterly.letterScore',
                'stock_earnings_quality_quarterly.leverage',
                'stock_earnings_quality_quarterly.profitability',
                'stock_earnings_quality_quarterly.score',

                'recommendation_totals.total_buy',
                'recommendation_totals.total_hold',
                'recommendation_totals.total_sell',
                'recommendation_totals.total_strongBuy',
                'recommendation_totals.total_strongSell',

                // sector metrics
                'stock_sector_metrics.peTTM',
                'stock_sector_metrics.revenueGrowthQuarterlyYoy',
                'stock_sector_metrics.payoutRatioTTM',

                
                'candle_data.close_price',
                'candle_data.first_price_of_month',
                'candle_data.first_price_of_year',

                'earnings_data.future_calendar_date',
                'earnings_data.revenue_estimate',
                'earnings_data.revenue_actual',
                // Ensure past_calendar_date logic is covered by grouping
                DB::raw('CASE
                            WHEN earnings_data.revenue_actual IS NOT NULL
                            THEN earnings_data.future_calendar_date
                            ELSE NULL
                        END')
            )
            ->get()
            ->toArray();

        return $results;
    }


    public static function getAllReferencesV3(Request $request): array
    {
        $hours     = now()->isMonday() ? 72 : 24;
    $from_date = $request->input('from_date')
               ?? now()->subHours($hours)->toDateTimeString();

        $currentMonth = date('Y-01-01') === date('Y-m-01') ? date('Y-m-01') : date('Y-m-01');
        $currentYear = date('Y-01-01');
        $currentDate = date('Y-m-d');
        $futureDate = date('Y-m-d', strtotime('+7 days'));

        $query = DB::table('stock_symbols')
        // Basic joins
        ->leftJoin('stock_company_news', 'stock_company_news.stock_id', '=', 'stock_symbols.id')
        ->leftJoin('stock_basic_financials_metric', 'stock_basic_financials_metric.stock_id', '=', 'stock_symbols.id')
        ->leftJoin('stock_price_target', 'stock_price_target.stock_id', '=', 'stock_symbols.id')
        ->leftJoin('stock_indicators', 'stock_indicators.stock_id', '=', 'stock_symbols.id')
        ->leftJoin('stock_trading_score', DB::raw('BINARY stock_trading_score.symbol'), '=', DB::raw('BINARY stock_symbols.symbol'))
        ->leftJoin('stock_symbol_info', DB::raw('BINARY stock_symbols.id'), '=', DB::raw('BINARY stock_symbol_info.stock_id'))
        ->leftJoin('stock_earnings_quality_quarterly', 'stock_earnings_quality_quarterly.stock_id', '=', 'stock_symbols.id')
        ->leftJoin('stock_sector_metrics', DB::raw('BINARY stock_sector_metrics.sector'), '=', DB::raw('BINARY stock_symbol_info.sector'))
        ->leftJoin(
            DB::raw('(SELECT stock_id, SUM(buy) as total_buy, SUM(hold) as total_hold, SUM(sell) as total_sell, SUM(strongBuy) as total_strongBuy, SUM(strongSell) as total_strongSell 
                      FROM stock_recommendation_trends 
                      GROUP BY stock_id) as recommendation_totals'),
            'recommendation_totals.stock_id',
            '=',
            'stock_symbols.id'
        )
        ->leftJoin(
            DB::raw('(SELECT stock_id, 
                             MAX(close_price) as current_price, 
                             MIN(CASE WHEN ts >= "' . $currentMonth . '" THEN close_price END) as first_price_of_month, 
                             MIN(CASE WHEN ts >= "' . $currentYear . '" THEN close_price END) as first_price_of_year 
                      FROM stock_candle_daily 
                      GROUP BY stock_id) as candle_data'),
            'candle_data.stock_id',
            '=',
            'stock_symbols.id'
        );

    //if ($from_date) {
        $query->where(DB::raw('BINARY stock_company_news.date_time'), '>=', $from_date);
    //}

    $results = $query->select(
            'stock_symbols.symbol',
            'stock_indicators.rsi',
            'stock_indicators.ema_50',
            'stock_indicators.sma_50',
            'stock_basic_financials_metric.pettm',
            'stock_basic_financials_metric.fifty_two_week_low',
            'stock_basic_financials_metric.fifty_two_week_high',
            'stock_basic_financials_metric.netmargin',
            'stock_price_target.number_analysts',
            'stock_price_target.target_high',
            'stock_price_target.target_low',
            'stock_price_target.target_mean',
            'stock_price_target.target_median',
            'stock_trading_score.technical_score',
            'stock_trading_score.fundamental_score',
            'stock_trading_score.news_sentiment_score',
            'stock_trading_score.analyst_score',
            'stock_trading_score.trade_engine_score',
            'stock_earnings_quality_quarterly.capitalAllocation',
            'stock_earnings_quality_quarterly.growth',
            'stock_earnings_quality_quarterly.letterScore',
            'stock_earnings_quality_quarterly.leverage',
            'stock_earnings_quality_quarterly.profitability',
            'stock_earnings_quality_quarterly.score',
            'recommendation_totals.total_buy',
            'recommendation_totals.total_hold',
            'recommendation_totals.total_sell',
            'recommendation_totals.total_strongBuy',
            'recommendation_totals.total_strongSell',
            'candle_data.current_price',
            'candle_data.first_price_of_month',
            'candle_data.first_price_of_year',
            DB::raw('(candle_data.current_price - candle_data.first_price_of_month) as price_difference_month'),
            DB::raw('(candle_data.current_price - candle_data.first_price_of_year) as price_difference_year'),
            'stock_symbol_info.market_cap',
            'stock_symbol_info.sector',
        )
        ->groupBy(
            'stock_symbols.symbol',
            'stock_indicators.rsi',
            'stock_indicators.ema_50',
            'stock_indicators.sma_50',
            'stock_basic_financials_metric.pettm',
            'stock_basic_financials_metric.fifty_two_week_low',
            'stock_basic_financials_metric.fifty_two_week_high',
            'stock_basic_financials_metric.netmargin',
            'stock_price_target.number_analysts',
            'stock_price_target.target_high',
            'stock_price_target.target_low',
            'stock_price_target.target_mean',
            'stock_price_target.target_median',
            'stock_trading_score.technical_score',
            'stock_trading_score.fundamental_score',
            'stock_trading_score.news_sentiment_score',
            'stock_trading_score.analyst_score',
            'stock_trading_score.trade_engine_score',
            'stock_earnings_quality_quarterly.capitalAllocation',
            'stock_earnings_quality_quarterly.growth',
            'stock_earnings_quality_quarterly.letterScore',
            'stock_earnings_quality_quarterly.leverage',
            'stock_earnings_quality_quarterly.profitability',
            'stock_earnings_quality_quarterly.score',
            'recommendation_totals.total_buy',
            'recommendation_totals.total_hold',
            'recommendation_totals.total_sell',
            'recommendation_totals.total_strongBuy',
            'recommendation_totals.total_strongSell',
            'candle_data.current_price',
            'candle_data.first_price_of_month',
            'candle_data.first_price_of_year',
            'stock_symbol_info.market_cap',
            'stock_symbol_info.sector',
        )
        ->orderBy('stock_symbol_info.market_cap', 'desc') // Order by market_cap descending
        ->limit(100) // Limit to top 100
        ->get()
        ->toArray();

    return $results;
}


    public static function getNewsBySector(Request $request) {
        $sector = $request->input('sector');
        $hours     = now()->isMonday() ? 72 : 24;
    $from_date = $request->input('from_date')
               ?? now()->subHours($hours)->toDateTimeString();

        // Clean up and decode the sector parameter
        $sectorsArray = array_map('trim', explode(',', urldecode($sector)));

        $query = DB::table('stock_symbols')
            ->join('stock_company_news', 'stock_company_news.stock_id', '=', 'stock_symbols.id') // Ensure only stocks with news
            ->join('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id') // Join stock_symbol_info for market_cap
            ->join('stocks_by_market_cap', 'stocks_by_market_cap.symbol', '=', 'stock_symbols.symbol')
            //->where('stocks_by_market_cap.processed', '=', 1)
            ->whereIn(DB::raw('BINARY stock_symbol_info.sector'), $sectorsArray)
            ->where('stock_symbol_info.market_cap', '>', 50000000000) // Filter by market_cap
            ->where(DB::raw('BINARY stock_company_news.date_time'), '>=', $from_date);

        return $query->select(
                'stock_symbols.symbol',
                DB::raw('JSON_ARRAYAGG(
                    JSON_OBJECT(
                        "headline", stock_company_news.headline, 
                        "summary", stock_company_news.summary
                    )
                ) AS news')
            )
            ->groupBy('stock_symbols.symbol')
            ->get()
            ->toArray();
    }

    public static function getAllNews(Request $request) {


       // $from_date = $request->input('from_date') ?? now()->subDay()->toDateTimeString(); // Default to last 24 hours
       
        // If the caller provided a from_date, use it.
    // Otherwise default to 72h on Mondays, 24h on every other day.
    $hours     = now()->isMonday() ? 72 : 24;
    $from_date = $request->input('from_date')
               ?? now()->subHours($hours)->toDateTimeString();
        $query = DB::table('stock_symbols')
            ->join('stock_company_news', 'stock_company_news.stock_id', '=', 'stock_symbols.id') // Ensure only stocks with news
            ->join('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id') // Join stock_symbol_info for market_cap
            ->join('stocks_by_market_cap', 'stocks_by_market_cap.symbol', '=', 'stock_symbols.symbol')
            //->where('stocks_by_market_cap.processed', '=', 1)
            ->where('stock_symbol_info.market_cap', '>', 100000000000) // Filter by market_cap
            ->where(DB::raw('BINARY stock_company_news.date_time'), '>=', $from_date);

        return $query->select(
                'stock_symbols.symbol',
                'stock_symbol_info.company_name',
                DB::raw('JSON_ARRAYAGG(
                    JSON_OBJECT(
                        "headline", stock_company_news.headline, 
                        "summary", stock_company_news.summary
                    )
                ) AS news')
            )
            ->groupBy('stock_symbols.symbol','stock_symbol_info.company_name')
            ->get()
            ->toArray();
    }

    public static function getAllNewsLimit(Request $request) {

        $hours     = now()->isMonday() ? 72 : 24;
        $from_date = $request->input('from_date')
                ?? now()->subHours($hours)->toDateTimeString();
            $query = DB::table('stock_symbols')
                ->join('stock_company_news', 'stock_company_news.stock_id', '=', 'stock_symbols.id') // Ensure only stocks with news
                ->join('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id') // Join stock_symbol_info for market_cap
                ->join('stocks_by_market_cap', 'stocks_by_market_cap.symbol', '=', 'stock_symbols.symbol')
                //->where('stocks_by_market_cap.processed', '=', 1)
                ->where(DB::raw('BINARY stock_company_news.date_time'), '>=', $from_date)
                ->orderBy('stock_symbol_info.market_cap', 'desc') // Order by latest news
                ->limit(500); // Limit to 100 results (adjust as needed)

            return $query->select(
                    'stock_symbols.symbol',
                    'stock_symbol_info.company_name',
                    'stock_symbol_info.sector',
                    DB::raw('JSON_ARRAYAGG(
                        JSON_OBJECT(
                            "headline", stock_company_news.headline, 
                            "summary", stock_company_news.summary
                        )
                    ) AS news')
                )
                ->groupBy('stock_symbols.symbol','stock_symbol_info.company_name','stock_symbol_info.sector')
                ->get()
                ->toArray();
    }

    public static function getAllNewsSmallCap(Request $request)
    {
        // If the caller provided a from_date, use it.
        // Otherwise, default to 72 hours on Mondays, 24 hours on other days.
        $hours = now()->isMonday() ? 72 : 24;
        $from_date = $request->input('from_date') ?? now()->subHours($hours)->toDateTimeString();

        $query = DB::table('stock_symbols')
            ->join('stock_company_news', 'stock_company_news.stock_id', '=', 'stock_symbols.id') // Ensure only stocks with news
            ->join('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id') // Join stock_symbol_info for market_cap
            ->join('stocks_by_market_cap', 'stocks_by_market_cap.symbol', '=', 'stock_symbols.symbol')
            //->where('stocks_by_market_cap.processed', '=', 1)
            //->whereBetween('stock_symbol_info.market_cap', ['2000000000', '10000000000'])
            ->where('stock_symbol_info.market_cap', '>=', DB::raw('2000000000'))
            ->where('stock_symbol_info.market_cap', '<=', DB::raw('10000000000')) // Filter by market_cap range
            ->where(DB::raw('BINARY stock_company_news.date_time'), '>=',$from_date);
        
        return $query->select(
                'stock_symbols.symbol',
                'stock_symbol_info.company_name',
                DB::raw('JSON_ARRAYAGG(
                    JSON_OBJECT(
                        "headline", stock_company_news.headline, 
                        "summary", stock_company_news.summary
                    )
                ) AS news')
            )
            ->groupBy('stock_symbols.symbol', 'stock_symbol_info.company_name')
            ->get()
            ->toArray();
    }


    public static function getAllNewsMidCap(Request $request) {
        // If the caller provided a from_date, use it.
    // Otherwise default to 72h on Mondays, 24h on every other day.
    $hours     = now()->isMonday() ? 72 : 24;
    $from_date = $request->input('from_date')
               ?? now()->subHours($hours)->toDateTimeString();


        $query = DB::table('stock_symbols')
            ->join('stock_company_news', 'stock_company_news.stock_id', '=', 'stock_symbols.id')
            ->join('stock_symbol_info',   'stock_symbols.id',         '=', 'stock_symbol_info.stock_id')
            // Only include symbols with market cap between 30 million and 2 billion
            ->where('stock_symbol_info.market_cap', '>=', DB::raw('10000000000'))
            ->where('stock_symbol_info.market_cap', '<=', DB::raw('50000000000')) 
            ->where(DB::raw('BINARY stock_company_news.date_time'), '>=', $from_date);
        
        return $query->select(
                'stock_symbols.symbol',
                DB::raw('JSON_ARRAYAGG(
                    JSON_OBJECT(
                        "headline", stock_company_news.headline, 
                        "summary",  stock_company_news.summary
                    )
                ) AS news')
            )
            ->groupBy('stock_symbols.symbol')
            ->get()
            ->toArray();
    }


        public static function getAllReferencesFixSpeed(Request $request): array
        {
            $symbols     = $request->input('symbols');
            $from_date   = $request->input('from_date');
            $symbolsArray = array_map('trim', explode(',', $symbols));

            //$currentMonth = date('Y-m-01');
            $currentMonth = date('Y-01-01') === date('Y-m-01') ? date('Y-m-01') : date('Y-m-01');
            $currentYear  = date('Y-01-01');
            $currentDate  = date('Y-m-d');
            $pastDate    = date('Y-m-d', strtotime('-7 days'));
            $futureDate   = date('Y-m-d', strtotime('+7 days'));

            // Only join news in a subquery to limit the number of news per symbol (e.g., 3 latest)
            $newsSub = DB::table('stock_company_news')
                ->select('stock_id', DB::raw('JSON_ARRAYAGG(JSON_OBJECT("headline", headline, "date_time", date_time, "summary", summary)) as news'))
                ->where('date_time', '>=', $from_date ?? now()->subDays(8)->toDateTimeString())
                ->groupBy('stock_id');

            $query = DB::table('stock_symbols')
            ->leftJoin('stock_basic_financials_metric', 'stock_basic_financials_metric.stock_id', '=', 'stock_symbols.id')
            ->leftJoin('stock_price_target', 'stock_price_target.stock_id', '=', 'stock_symbols.id')
            ->leftJoin('stock_indicators', 'stock_indicators.stock_id', '=', 'stock_symbols.id')
            ->leftJoin('stock_trading_score', 'stock_trading_score.symbol', '=', 'stock_symbols.symbol')
            ->leftJoin('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')
            ->leftJoin('stock_earnings_quality_quarterly', 'stock_earnings_quality_quarterly.stock_id', '=', 'stock_symbols.id')
            ->leftJoin('stock_sector_metrics', 'stock_sector_metrics.sector', '=', 'stock_symbol_info.sector')
            ->leftJoin(
                DB::raw('(SELECT stock_id, SUM(buy) as total_buy, SUM(hold) as total_hold, SUM(sell) as total_sell, SUM(strongBuy) as total_strongBuy, SUM(strongSell) as total_strongSell 
                        FROM stock_recommendation_trends 
                        GROUP BY stock_id) as recommendation_totals'),
                'recommendation_totals.stock_id',
                '=',
                'stock_symbols.id'
            )
            ->leftJoin(
    DB::raw('(
        SELECT
            t1.stock_id,
            t1.close_price,
            t1.ts,
            -- First price of month
            (
                SELECT close_price
                FROM stock_candle_daily
                WHERE stock_id = t1.stock_id AND ts >= "' . $currentMonth . '"
                ORDER BY ts ASC
                LIMIT 1
            ) as first_price_of_month,
            -- First price of year
            (
                SELECT close_price
                FROM stock_candle_daily
                WHERE stock_id = t1.stock_id AND ts >= "' . $currentYear . '"
                ORDER BY ts ASC
                LIMIT 1
            ) as first_price_of_year
        FROM stock_candle_daily t1
        INNER JOIN (
            SELECT stock_id, MAX(ts) as max_ts
            FROM stock_candle_daily
            GROUP BY stock_id
        ) t2 ON t1.stock_id = t2.stock_id AND t1.ts = t2.max_ts
    ) as candle_data'),
    'candle_data.stock_id',
    '=',
    'stock_symbols.id'
)
            /**->leftJoin(
                DB::raw('(
                    SELECT
                        sec.symbol,
                        sec.cal_date AS future_calendar_date,
                        sec.revenue_estimate,
                        sec.revenue_actual
                    FROM stock_earnings_calendar sec
                    INNER JOIN (
                        SELECT symbol, MAX(cal_date) AS max_cal_date
                        FROM stock_earnings_calendar
                        GROUP BY symbol
                    ) latest ON latest.symbol = sec.symbol AND latest.max_cal_date = sec.cal_date
                ) AS earnings_data'),
                'earnings_data.symbol',
                '=',
                'stock_symbols.symbol'
            )**/
            // Subquery for past 7 days earnings
        ->leftJoin(
            DB::raw('(
                SELECT
                    symbol,
                    cal_date AS past_calendar_date,
                    revenue_actual
                FROM stock_earnings_calendar
                WHERE cal_date BETWEEN "' . $pastDate . '" AND "' . $currentDate . '"
                ORDER BY cal_date DESC
            ) AS earnings_data_past'),
            'earnings_data_past.symbol',
            '=',
            'stock_symbols.symbol'
        )
        // Subquery for next 7 days earnings
        ->leftJoin(
            DB::raw('(
                SELECT
                    symbol,
                    cal_date AS future_calendar_date,
                    revenue_estimate
                FROM stock_earnings_calendar
                WHERE cal_date BETWEEN "' . $currentDate . '" AND "' . $futureDate . '"
                ORDER BY cal_date ASC
            ) AS earnings_data_future'),
            'earnings_data_future.symbol',
            '=',
            'stock_symbols.symbol'
        )
            ->leftJoinSub($newsSub, 'news_agg', 'news_agg.stock_id', '=', 'stock_symbols.id')
            ->whereIn('stock_symbols.symbol', $symbolsArray);

        $results = $query->select(
                'stock_symbols.symbol',
                'stock_indicators.rsi',
                'stock_indicators.ema_50',
                'stock_indicators.sma_50',
                'stock_symbol_info.sector',
                'stock_basic_financials_metric.pettm',
                'stock_basic_financials_metric.fifty_two_week_low',
                'stock_basic_financials_metric.fifty_two_week_high',
                'stock_basic_financials_metric.netmargin',            
                'stock_trading_score.technical_score',
                'stock_trading_score.fundamental_score',
                'stock_trading_score.news_sentiment_score',
                'stock_trading_score.analyst_score',
                'stock_trading_score.trade_engine_score',
                'stock_price_target.number_analysts',
                'stock_price_target.target_high',
                'stock_price_target.target_low',
                'stock_price_target.target_mean',
                'stock_price_target.target_median',
                'stock_earnings_quality_quarterly.capitalAllocation',
                'stock_earnings_quality_quarterly.growth',
                'stock_earnings_quality_quarterly.letterScore',
                'stock_earnings_quality_quarterly.leverage',
                'stock_earnings_quality_quarterly.profitability',
                'stock_earnings_quality_quarterly.score AS earnings_quality_score',
                'recommendation_totals.total_buy',
                'recommendation_totals.total_hold',
                'recommendation_totals.total_sell',
                'recommendation_totals.total_strongBuy',
                'recommendation_totals.total_strongSell',
                'stock_sector_metrics.peTTM AS sector_peTTM',
                'stock_sector_metrics.revenueGrowthQuarterlyYoy AS sector_revenueGrowthQuarterlyYoy',
                'stock_sector_metrics.payoutRatioTTM AS sector_payoutRatioTTM',
                'candle_data.close_price',
                'candle_data.first_price_of_month',
                'candle_data.first_price_of_year',
                DB::raw('(candle_data.close_price - candle_data.first_price_of_month) as price_difference_month'),
                DB::raw('(candle_data.close_price - candle_data.first_price_of_year) as price_difference_year'),
                // upcoming earnings flag + estimate
                DB::raw('CASE
                                WHEN earnings_data_future.future_calendar_date BETWEEN "' . $currentDate . '" AND "' . $futureDate . '"
                                THEN "yes" ELSE "no"
                            END AS has_earnings_within_7_days'),
                 DB::raw('CASE
                                WHEN earnings_data_past.past_calendar_date BETWEEN "' . $currentDate . '" AND "' . $pastDate . '"
                                THEN "yes" ELSE "no"
                            END AS had_earnings_within_7_days'),

                'earnings_data_past.past_calendar_date',
                'earnings_data_past.revenue_actual',
                'earnings_data_future.future_calendar_date',
                'earnings_data_future.revenue_estimate',
                'news_agg.news'
            )
            ->get()
            ->toArray();

        return $results;
    }

    public static function getStockMetrics(Request $request): array
    {
        $symbols     = $request->input('symbols');
        $from_date   = $request->input('from_date');
        $symbolsArray = array_map('trim', explode(',', $symbols));

        //$currentMonth = date('Y-m-01');
        $currentMonth = date('Y-01-01') === date('Y-m-01') ? date('Y-m-01') : date('Y-m-01');
        $currentYear  = date('Y-01-01');
        $currentDate  = date('Y-m-d');
            $pastDate    = date('Y-m-d', strtotime('-7 days'));
            $futureDate   = date('Y-m-d', strtotime('+7 days'));       

        $query = DB::table('stock_symbols')
        ->leftJoin('stock_basic_financials_metric', 'stock_basic_financials_metric.stock_id', '=', 'stock_symbols.id')
        ->leftJoin('stock_price_target', 'stock_price_target.stock_id', '=', 'stock_symbols.id')
        ->leftJoin('stock_indicators', 'stock_indicators.stock_id', '=', 'stock_symbols.id')
        ->leftJoin('stock_trading_score', 'stock_trading_score.symbol', '=', 'stock_symbols.symbol')
        ->leftJoin('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')
        ->leftJoin('stock_earnings_quality_quarterly', 'stock_earnings_quality_quarterly.stock_id', '=', 'stock_symbols.id')
        ->leftJoin('stock_sector_metrics', 'stock_sector_metrics.sector', '=', 'stock_symbol_info.sector')
        ->leftJoin(
            DB::raw('(SELECT stock_id, SUM(buy) as total_buy, SUM(hold) as total_hold, SUM(sell) as total_sell, SUM(strongBuy) as total_strongBuy, SUM(strongSell) as total_strongSell 
                      FROM stock_recommendation_trends 
                      GROUP BY stock_id) as recommendation_totals'),
            'recommendation_totals.stock_id',
            '=',
            'stock_symbols.id'
        )
        ->leftJoin(
    DB::raw('(
        SELECT
            t1.stock_id,
            t1.close_price,
            t1.ts,
            -- First price of month
            (
                SELECT close_price
                FROM stock_candle_daily
                WHERE stock_id = t1.stock_id AND ts >= "' . $currentMonth . '"
                ORDER BY ts ASC
                LIMIT 1
            ) as first_price_of_month,
            -- First price of year
            (
                SELECT close_price
                FROM stock_candle_daily
                WHERE stock_id = t1.stock_id AND ts >= "' . $currentYear . '"
                ORDER BY ts ASC
                LIMIT 1
            ) as first_price_of_year
        FROM stock_candle_daily t1
        INNER JOIN (
            SELECT stock_id, MAX(ts) as max_ts
            FROM stock_candle_daily
            GROUP BY stock_id
        ) t2 ON t1.stock_id = t2.stock_id AND t1.ts = t2.max_ts
    ) as candle_data'),
    'candle_data.stock_id',
    '=',
    'stock_symbols.id'
)

        // Subquery for past 7 days earnings
        ->leftJoin(
            DB::raw('(
                SELECT
                    symbol,
                    cal_date AS past_calendar_date,
                    revenue_actual
                FROM stock_earnings_calendar
                WHERE cal_date BETWEEN "' . $pastDate . '" AND "' . $currentDate . '"
                ORDER BY cal_date DESC
            ) AS earnings_data_past'),
            'earnings_data_past.symbol',
            '=',
            'stock_symbols.symbol'
        )
        // Subquery for next 7 days earnings
        ->leftJoin(
            DB::raw('(
                SELECT
                    symbol,
                    cal_date AS future_calendar_date,
                    revenue_estimate
                FROM stock_earnings_calendar
                WHERE cal_date BETWEEN "' . $currentDate . '" AND "' . $futureDate . '"
                ORDER BY cal_date ASC
            ) AS earnings_data_future'),
            'earnings_data_future.symbol',
            '=',
            'stock_symbols.symbol'
        )
        
        ->whereIn('stock_symbols.symbol', $symbolsArray);

        $results = $query->select(
                'stock_symbols.symbol',
                'stock_indicators.rsi',
                'stock_indicators.ema_50',
                'stock_indicators.sma_50',
                'stock_symbol_info.sector',
                'stock_symbol_info.company_name',
                'stock_symbol_info.market_cap',
                'stock_basic_financials_metric.pettm',
                'stock_basic_financials_metric.fifty_two_week_low',
                'stock_basic_financials_metric.fifty_two_week_high',
                'stock_basic_financials_metric.netmargin',            
                'stock_trading_score.technical_score',
                'stock_trading_score.fundamental_score',
                'stock_trading_score.news_sentiment_score',
                'stock_trading_score.analyst_score',
                'stock_trading_score.trade_engine_score',
                'stock_price_target.number_analysts',
                'stock_price_target.target_high',
                'stock_price_target.target_low',
                'stock_price_target.target_mean',
                'stock_price_target.target_median',
                'stock_earnings_quality_quarterly.capitalAllocation',
                'stock_earnings_quality_quarterly.growth',
                'stock_earnings_quality_quarterly.letterScore',
                'stock_earnings_quality_quarterly.leverage',
                'stock_earnings_quality_quarterly.profitability',
                'stock_earnings_quality_quarterly.score AS earnings_quality_score',
                'recommendation_totals.total_buy',
                'recommendation_totals.total_hold',
                'recommendation_totals.total_sell',
                'recommendation_totals.total_strongBuy',
                'recommendation_totals.total_strongSell',
                'stock_sector_metrics.peTTM AS sector_peTTM',
                'stock_sector_metrics.revenueGrowthQuarterlyYoy AS sector_revenueGrowthQuarterlyYoy',
                'stock_sector_metrics.payoutRatioTTM AS sector_payoutRatioTTM',
                'candle_data.close_price',
                'candle_data.first_price_of_month',
                'candle_data.first_price_of_year',
                DB::raw('(candle_data.close_price - candle_data.first_price_of_month) as price_difference_month'),
                DB::raw('(candle_data.close_price - candle_data.first_price_of_year) as price_difference_year'),
                // upcoming earnings flag + estimate
                DB::raw('CASE
                                WHEN earnings_data_future.future_calendar_date BETWEEN "' . $currentDate . '" AND "' . $futureDate . '"
                                THEN "yes" ELSE "no"
                            END AS has_earnings_within_7_days'),
                 DB::raw('CASE
                                WHEN earnings_data_past.past_calendar_date BETWEEN "' . $currentDate . '" AND "' . $pastDate . '"
                                THEN "yes" ELSE "no"
                            END AS had_earnings_within_7_days'),

                'earnings_data_past.past_calendar_date',
                'earnings_data_past.revenue_actual',
                'earnings_data_future.future_calendar_date',
                'earnings_data_future.revenue_estimate',
            )
            ->get()
            ->toArray();

        return $results;
    }


    public static function getStockMetricsSingle(string $symbol): array
    {
        $symbols     = $symbol;
        $from_date   = now()->subDays(100)->toDateTimeString();
        $symbolsArray = array_map('trim', explode(',', $symbols));

        //$currentMonth = date('Y-m-01');
        $currentMonth = date('Y-01-01') === date('Y-m-01') ? date('Y-m-01') : date('Y-m-01');
        $currentYear  = date('Y-01-01');
        $currentDate  = date('Y-m-d');
            $pastDate    = date('Y-m-d', strtotime('-7 days'));
            $futureDate   = date('Y-m-d', strtotime('+7 days'));       

        $query = DB::table('stock_symbols')
        ->leftJoin('stock_basic_financials_metric', 'stock_basic_financials_metric.stock_id', '=', 'stock_symbols.id')
        ->leftJoin('stock_price_target', 'stock_price_target.stock_id', '=', 'stock_symbols.id')
        ->leftJoin('stock_indicators', 'stock_indicators.stock_id', '=', 'stock_symbols.id')
        ->leftJoin('stock_trading_score', 'stock_trading_score.symbol', '=', 'stock_symbols.symbol')
        ->leftJoin('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')
        ->leftJoin('stock_earnings_quality_quarterly', 'stock_earnings_quality_quarterly.stock_id', '=', 'stock_symbols.id')
        ->leftJoin('stock_sector_metrics', 'stock_sector_metrics.sector', '=', 'stock_symbol_info.sector')
        ->leftJoin(
            DB::raw('(SELECT stock_id, SUM(buy) as total_buy, SUM(hold) as total_hold, SUM(sell) as total_sell, SUM(strongBuy) as total_strongBuy, SUM(strongSell) as total_strongSell 
                      FROM stock_recommendation_trends 
                      GROUP BY stock_id) as recommendation_totals'),
            'recommendation_totals.stock_id',
            '=',
            'stock_symbols.id'
        )
        ->leftJoin(
    DB::raw('(
        SELECT
            t1.stock_id,
            t1.close_price,
            t1.ts,
            -- First price of month
            (
                SELECT close_price
                FROM stock_candle_daily
                WHERE stock_id = t1.stock_id AND ts >= "' . $currentMonth . '"
                ORDER BY ts ASC
                LIMIT 1
            ) as first_price_of_month,
            -- First price of year
            (
                SELECT close_price
                FROM stock_candle_daily
                WHERE stock_id = t1.stock_id AND ts >= "' . $currentYear . '"
                ORDER BY ts ASC
                LIMIT 1
            ) as first_price_of_year
        FROM stock_candle_daily t1
        INNER JOIN (
            SELECT stock_id, MAX(ts) as max_ts
            FROM stock_candle_daily
            GROUP BY stock_id
        ) t2 ON t1.stock_id = t2.stock_id AND t1.ts = t2.max_ts
    ) as candle_data'),
    'candle_data.stock_id',
    '=',
    'stock_symbols.id'
)

        // Subquery for past 7 days earnings
        ->leftJoin(
            DB::raw('(
                SELECT
                    symbol,
                    cal_date AS past_calendar_date,
                    revenue_actual
                FROM stock_earnings_calendar
                WHERE cal_date BETWEEN "' . $pastDate . '" AND "' . $currentDate . '"
                ORDER BY cal_date DESC
            ) AS earnings_data_past'),
            'earnings_data_past.symbol',
            '=',
            'stock_symbols.symbol'
        )
        // Subquery for next 7 days earnings
        ->leftJoin(
            DB::raw('(
                SELECT
                    symbol,
                    cal_date AS future_calendar_date,
                    revenue_estimate
                FROM stock_earnings_calendar
                WHERE cal_date BETWEEN "' . $currentDate . '" AND "' . $futureDate . '"
                ORDER BY cal_date ASC
            ) AS earnings_data_future'),
            'earnings_data_future.symbol',
            '=',
            'stock_symbols.symbol'
        )
        
        ->whereIn('stock_symbols.symbol', $symbolsArray);

        $results = $query->select(
                'stock_symbols.symbol',
                'stock_indicators.rsi',
                'stock_indicators.ema_50',
                'stock_indicators.sma_50',
                'stock_symbol_info.sector',
                'stock_symbol_info.company_name',
                'stock_symbol_info.market_cap',
                'stock_basic_financials_metric.pettm',
                'stock_basic_financials_metric.fifty_two_week_low',
                'stock_basic_financials_metric.fifty_two_week_high',
                'stock_basic_financials_metric.netmargin',            
                'stock_trading_score.technical_score',
                'stock_trading_score.fundamental_score',
                'stock_trading_score.news_sentiment_score',
                'stock_trading_score.analyst_score',
                'stock_trading_score.trade_engine_score',
                'stock_price_target.number_analysts',
                'stock_price_target.target_high',
                'stock_price_target.target_low',
                'stock_price_target.target_mean',
                'stock_price_target.target_median',
                'stock_earnings_quality_quarterly.capitalAllocation',
                'stock_earnings_quality_quarterly.growth',
                'stock_earnings_quality_quarterly.letterScore',
                'stock_earnings_quality_quarterly.leverage',
                'stock_earnings_quality_quarterly.profitability',
                'stock_earnings_quality_quarterly.score AS earnings_quality_score',
                'recommendation_totals.total_buy',
                'recommendation_totals.total_hold',
                'recommendation_totals.total_sell',
                'recommendation_totals.total_strongBuy',
                'recommendation_totals.total_strongSell',
                'stock_sector_metrics.peTTM AS sector_peTTM',
                'stock_sector_metrics.revenueGrowthQuarterlyYoy AS sector_revenueGrowthQuarterlyYoy',
                'stock_sector_metrics.payoutRatioTTM AS sector_payoutRatioTTM',
                'candle_data.close_price',
                'candle_data.first_price_of_month',
                'candle_data.first_price_of_year',
                DB::raw('(candle_data.close_price - candle_data.first_price_of_month) as price_difference_month'),
                DB::raw('(candle_data.close_price - candle_data.first_price_of_year) as price_difference_year'),
                // upcoming earnings flag + estimate
                DB::raw('CASE
                                WHEN earnings_data_future.future_calendar_date BETWEEN "' . $currentDate . '" AND "' . $futureDate . '"
                                THEN "yes" ELSE "no"
                            END AS has_earnings_within_7_days'),
                 DB::raw('CASE
                                WHEN earnings_data_past.past_calendar_date BETWEEN "' . $currentDate . '" AND "' . $pastDate . '"
                                THEN "yes" ELSE "no"
                            END AS had_earnings_within_7_days'),

                'earnings_data_past.past_calendar_date',
                'earnings_data_past.revenue_actual',
                'earnings_data_future.future_calendar_date',
                'earnings_data_future.revenue_estimate',
            )
            ->get()
            ->toArray();

        return $results;
    }



    public static function getAllTop100StocksByMarketCap(Request $request): array
    {
        $currentMonth = date('Y-m-01');
        $currentYear  = date('Y-01-01');

        $query = DB::table('stock_symbols')
            ->leftJoin('stock_basic_financials_metric', 'stock_basic_financials_metric.stock_id', '=', 'stock_symbols.id')
            ->leftJoin('stock_price_target', 'stock_price_target.stock_id', '=', 'stock_symbols.id')
            // Only latest stock_indicators per stock
            ->leftJoin(DB::raw('(
                SELECT *
                FROM stock_indicators
                WHERE id IN (
                    SELECT MAX(id) FROM stock_indicators GROUP BY stock_id
                )
            ) as stock_indicators'), 'stock_indicators.stock_id', '=', 'stock_symbols.id')
            ->leftJoin('stock_trading_score', 'stock_trading_score.symbol', '=', 'stock_symbols.symbol')
            ->leftJoin('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')
            // Only latest earnings_quality_quarterly per stock
            ->leftJoin(DB::raw('(
                SELECT *
                FROM stock_earnings_quality_quarterly
                WHERE id IN (
                    SELECT MAX(id) FROM stock_earnings_quality_quarterly GROUP BY stock_id
                )
            ) as stock_earnings_quality_quarterly'), 'stock_earnings_quality_quarterly.stock_id', '=', 'stock_symbols.id')
            ->leftJoin('stock_sector_metrics', 'stock_sector_metrics.sector', '=', 'stock_symbol_info.sector')
            ->leftJoin(
                DB::raw('(SELECT stock_id, SUM(buy) as total_buy, SUM(hold) as total_hold, SUM(sell) as total_sell, SUM(strongBuy) as total_strongBuy, SUM(strongSell) as total_strongSell 
                FROM stock_recommendation_trends 
                GROUP BY stock_id) as recommendation_totals'),
                'recommendation_totals.stock_id',
                '=',
                'stock_symbols.id'
            )
            // Only latest candle per stock
            ->leftJoin(DB::raw('(
                SELECT t1.stock_id, t1.close_price, t1.ts,
                    (
                        SELECT close_price
                        FROM stock_candle_daily
                        WHERE stock_id = t1.stock_id AND ts >= "' . $currentMonth . '"
                        ORDER BY ts ASC
                        LIMIT 1
                    ) as first_price_of_month,
                    (
                        SELECT close_price
                        FROM stock_candle_daily
                        WHERE stock_id = t1.stock_id AND ts >= "' . $currentYear . '"
                        ORDER BY ts ASC
                        LIMIT 1
                    ) as first_price_of_year
                FROM stock_candle_daily t1
                INNER JOIN (
                    SELECT stock_id, MAX(ts) as max_ts
                    FROM stock_candle_daily
                    GROUP BY stock_id
                ) t2 ON t1.stock_id = t2.stock_id AND t1.ts = t2.max_ts
            ) as candle_data'), 'candle_data.stock_id', '=', 'stock_symbols.id')
        ->whereIn('stock_symbol_info.currency', ['USD', 'CAD']);

    $results = $query->select(
            'stock_symbols.symbol',
            'stock_indicators.rsi',
            'stock_indicators.ema_50',
            'stock_indicators.sma_50',
            'stock_symbol_info.sector',
            'stock_symbol_info.company_name',
            'stock_symbol_info.market_cap',
            'stock_basic_financials_metric.pettm',
            'stock_basic_financials_metric.fifty_two_week_low',
            'stock_basic_financials_metric.fifty_two_week_high',
            'stock_basic_financials_metric.netmargin',            
            'stock_trading_score.technical_score',
            'stock_trading_score.fundamental_score',
            'stock_trading_score.news_sentiment_score',
            'stock_trading_score.analyst_score',
            'stock_trading_score.trade_engine_score',
            'stock_price_target.number_analysts',
            'stock_price_target.target_high',
            'stock_price_target.target_low',
            'stock_price_target.target_mean',
            'stock_price_target.target_median',
            'stock_earnings_quality_quarterly.capitalAllocation',
            'stock_earnings_quality_quarterly.growth',
            'stock_earnings_quality_quarterly.letterScore',
            'stock_earnings_quality_quarterly.leverage',
            'stock_earnings_quality_quarterly.profitability',
            'stock_earnings_quality_quarterly.score AS earnings_quality_score',
            'recommendation_totals.total_buy',
            'recommendation_totals.total_hold',
            'recommendation_totals.total_sell',
            'recommendation_totals.total_strongBuy',
            'recommendation_totals.total_strongSell',
            'stock_sector_metrics.peTTM AS sector_peTTM',
            'stock_sector_metrics.revenueGrowthQuarterlyYoy AS sector_revenueGrowthQuarterlyYoy',
            'stock_sector_metrics.payoutRatioTTM AS sector_payoutRatioTTM',
            'candle_data.close_price',
            'candle_data.first_price_of_month',
            'candle_data.first_price_of_year',
            DB::raw('(candle_data.close_price - candle_data.first_price_of_month) as price_difference_month'),
            DB::raw('(candle_data.close_price - candle_data.first_price_of_year) as price_difference_year')
        )
        ->orderBy('stock_symbol_info.market_cap', 'desc')
        ->limit(100)
        ->get()
        ->toArray();

    return $results;
}
}