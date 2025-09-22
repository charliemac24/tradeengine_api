<?php

namespace App\Http\Controllers\system;

use App\Http\Controllers\Controller;
use App\Models\system\StockEventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockEventLoggerController extends Controller
{
    /**
     * Log stock events based on price and market data conditions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logStockEvent(Request $request): \Illuminate\Http\JsonResponse
    {
        // Validate the symbol parameter
        $request->validate([
            'symbol' => 'required|string|exists:stock_symbols,symbol', // Ensure the symbol exists in the stock_symbols table
        ]);

        // Fetch the stock data for the given symbol
        $symbol = $request->input('symbol');
        $stock = $this->getStockData($symbol);

        

        // Generate events for the stock           
        $priceAndMarketEvents = $this->generatePriceAndMarketData($stock);
        $earningsEvents = $this->generateEarningsEvents($stock);
        $newsSentiments = $this->generateNewsSentiments($stock);
        $analystTargetChanges = $this->generateAnalystTargetChanges($stock);
        $insiderInstitutional = $this->generateInsiderInstitutional($stock);
        $corporateEventsFillings = $this->generateCorporateEventsFillings($stock);

        // Combine all events
        $events = array_merge($priceAndMarketEvents, $earningsEvents, $newsSentiments, $analystTargetChanges, $insiderInstitutional, $corporateEventsFillings);

        // Log each event
        foreach ($events as $message) {
            StockEventLogger::logEvent($stock['stock_id'], $message);
        }
        return response()->json(['message' => 'Stock events logged successfully']);
    }

    /**
     * Generate stock events based on various conditions.
     *
     * @param array $stock The stock data.
     * @return array The list of generated events.
     */
    private function generatePriceAndMarketData(array $stock): array
    {
        $events = [];

        // Daily, Weekly, Monthly Movement
        if (abs($stock['daily_change_percentage']) > 5) {
            $events[] = "{$stock['symbol']} moved more than 5% today.";
        }
        if (abs($stock['weekly_change_percentage']) > 10) {
            $events[] = "{$stock['symbol']} moved more than 10% this week.";
        }
        if (abs($stock['monthly_change_percentage']) > 20) {
            $events[] = "{$stock['symbol']} moved more than 20% this month.";
        }

        // Technical Signals
        if ($stock['rsi'] > 70) {
            $events[] = "{$stock['symbol']} RSI is above 70 — a bullish signal.";
        }
        if ($stock['ema_50'] < $stock['current_price']) {
            $events[] = "{$stock['symbol']} broke above its 50-day EMA.";
        }

        // Volatility Spikes or Volume Surges
        if ($stock['volatility'] > $stock['average_volatility'] * 1.5) {
            $events[] = "{$stock['symbol']} experienced a volatility spike.";
        }
        if ($stock['volume'] > $stock['average_volume'] * 2) {
            $events[] = "{$stock['symbol']} experienced a volume surge.";
        }

        // 52-Week High/Low Proximity Alerts
        if (abs($stock['current_price'] - $stock['fifty_two_week_high']) <= $stock['fifty_two_week_high'] * 0.05) {
            $events[] = "{$stock['symbol']} is within 5% of its 52-week high.";
        }
        if (abs($stock['current_price'] - $stock['fifty_two_week_low']) <= $stock['fifty_two_week_low'] * 0.05) {
            $events[] = "{$stock['symbol']} is within 5% of its 52-week low.";
        }

        return $events;
    }

    /**
     * Generate earnings-related events based on stock data.
     *
     * @param array $stock The stock data.
     * @return array The list of generated events.
     */
    private function generateEarningsEvents(array $stock): array
    {
        $events = [];

        // Upcoming earnings reminders
        $earningsDate = Carbon::parse($stock['earnings_date']);
        if (!empty($stock['earnings_date']) && now()->diffInDays($stock['earnings_date'], false) <= 7) {
            if( $earningsDate->format('Y-m-d') > now()->format('Y-m-d') ){                
                $events[] = "{$stock['symbol']} has an upcoming earnings report on {$earningsDate->format('Y-m-d')}.";
            }
        }

        // Recent earnings beats/misses vs expectations
        if (!empty($stock['actual_eps']) && !empty($stock['expected_eps'])) {
            if ($stock['actual_eps'] > $stock['expected_eps']) {
                $events[] = "{$stock['symbol']} beat earnings expectations with an EPS of {$stock['actual_eps']} vs expected {$stock['expected_eps']}.";
            } elseif ($stock['actual_eps'] < $stock['expected_eps']) {
                $events[] = "{$stock['symbol']} missed earnings expectations with an EPS of {$stock['actual_eps']} vs expected {$stock['expected_eps']}.";
            }
        }

        // Revisions in EPS/Revenue outlook
        if (!empty($stock['revised_eps']) && !empty($stock['previous_eps'])) {
            if ($stock['revised_eps'] > $stock['previous_eps']) {
                $events[] = "{$stock['symbol']} had an upward revision in EPS outlook from {$stock['previous_eps']} to {$stock['revised_eps']}.";
            } elseif ($stock['revised_eps'] < $stock['previous_eps']) {
                $events[] = "{$stock['symbol']} had a downward revision in EPS outlook from {$stock['previous_eps']} to {$stock['revised_eps']}.";
            }
        }

        if (!empty($stock['revised_revenue']) && !empty($stock['previous_revenue'])) {
            if ($stock['revised_revenue'] > $stock['previous_revenue']) {
                $events[] = "{$stock['symbol']} had an upward revision in revenue outlook from {$stock['previous_revenue']} to {$stock['revised_revenue']}.";
            } elseif ($stock['revised_revenue'] < $stock['previous_revenue']) {
                $events[] = "{$stock['symbol']} had a downward revision in revenue outlook from {$stock['previous_revenue']} to {$stock['revised_revenue']}.";
            }
        }

        // Changes in earnings quality score
        if (!empty($stock['earnings_quality_score']) && !empty($stock['previous_quality_score'])) {
            if ($stock['earnings_quality_score'] > $stock['previous_quality_score']) {
                $events[] = "{$stock['symbol']} improved its earnings quality score from {$stock['previous_quality_score']} to {$stock['earnings_quality_score']}.";
            } elseif ($stock['earnings_quality_score'] < $stock['previous_quality_score']) {
                $events[] = "{$stock['symbol']} saw a decline in its earnings quality score from {$stock['previous_quality_score']} to {$stock['earnings_quality_score']}.";
            }
        }

        return $events;
    }


    /**
     * Generate news sentiment-related events based on stock data.
     *
     * @param array $stock The stock data.
     * @return array The list of generated events.
     */
    private function generateNewsSentiments(array $stock): array
    {
        $events = [];

        // Breaking news headlines per stock
        if (!empty($stock['breaking_news_headlines'])) {
            foreach ($stock['breaking_news_headlines'] as $headline) {
                $events[] = "{$stock['symbol']} breaking news: {$headline}.";
            }
        }

        // Shift in news sentiment or social buzz
        if (!empty($stock['news_sentiment']) && !empty($stock['previous_news_sentiment'])) {
            if ($stock['news_sentiment'] > $stock['previous_news_sentiment']) {
                $events[] = "{$stock['symbol']} news sentiment is trending bullish, up by " . 
                            abs($stock['news_sentiment'] - $stock['previous_news_sentiment']) . "%.";
            } elseif ($stock['news_sentiment'] < $stock['previous_news_sentiment']) {
                $events[] = "{$stock['symbol']} news sentiment is trending bearish, down by " . 
                            abs($stock['news_sentiment'] - $stock['previous_news_sentiment']) . "%.";
            }
        }

        // Volume of mentions rising across social platforms
        if (!empty($stock['social_mentions']) && !empty($stock['average_social_mentions'])) {
            if ($stock['social_mentions'] > $stock['average_social_mentions'] * 1.5) {
                $events[] = "{$stock['symbol']} is trending on social platforms with mentions up by " . 
                            round(($stock['social_mentions'] / $stock['average_social_mentions'] - 1) * 100) . "%.";
            }
        }

        // Include articles produced by Ed’s Idea Engine
       /* if (!empty($stock['eds_idea_engine_articles'])) {
            foreach ($stock['eds_idea_engine_articles'] as $article) {
                $events[] = "{$stock['symbol']} featured in Ed's Idea Engine: {$article}.";
            }
        }
        **/
        return $events;
    }

    /**
     * Generate analyst target-related events based on stock data.
     *
     * @param array $stock The stock data.
     * @return array The list of generated events.
     */
    private function generateAnalystTargetChanges(array $stock): array
    {
        $events = [];

        // New analyst ratings or changes
        $symbol = $stock['symbol'] ?? 'N/A';
        $company = $stock['upgrade_company'] ?? 'N/A';
        $from = $stock['from_grade'] ?? 'N/A';
        $to = $stock['to_grade'] ?? 'N/A';
        $when = $stock['grade_time'] ?? 'N/A';
        $verb    = $stock['action'] === 'up' || $stock['action'] === 'main'
                    ? 'upgraded'
                    : ($stock['action'] === 'down' ? 'downgraded' : 'changed');
        if ($from !== $to) {
            $events[] = "{$symbol} — {$company} was {$verb} from “{$from}” to “{$to}” on {$when}.";
        }

        // Consensus shifts (e.g., from hold to buy)
        /**if (!empty($stock['latest_consensus']) && !empty($stock['previous_consensus'])) {
            if ($stock['latest_consensus'] !== $stock['previous_consensus']) {
                $events[] = "{$stock['symbol']} consensus shifted from '{$stock['previous_consensus']}' to '{$stock['latest_consensus']}'.";
            }
        }**/

        // Target price changes with commentary
        if (!empty($stock['latest_target_price']) && !empty($stock['previous_target_price'])) {
            if ($stock['latest_target_price'] !== $stock['previous_target_price']) {
                $priceChange = $stock['latest_target_price'] - $stock['previous_target_price'];
                $direction = $priceChange > 0 ? 'increased' : 'decreased';
                $events[] = "{$stock['symbol']} target price {$direction} from {$stock['previous_target_price']} to {$stock['latest_target_price']}.";
            }
        }

        return $events;
    }


    /**
     * Generate insider and institutional-related events based on stock data.
     *
     * @param array $stock The stock data.
     * @return array The list of generated events.
     */
    private function generateInsiderInstitutional(array $stock): array
    {
        $events = [];

        // New insider buys/sells and size
        if (!empty($stock['type'])) {
            $type = $stock['type'] === 'P' ? 'purchased' : 'sold';
            $events[] = "💡 {$stock['symbol']} insiders {$type} {$stock['amount']} worth of shares.";
        }

        // Changes in top institutional positions
        if (!empty($stock['name'])) {
            $direction = $stock['change'] > 0 ? 'increased' : 'decreased';
            $events[] = "💡 {$stock['name']} {$direction} its stake in {$stock['symbol']} by " . abs($stock['change']) . "%.";
        }        

        // Notable new fund entries or exits
        /**if (!empty($stock['fund_entries_exits'])) {
            foreach ($stock['fund_entries_exits'] as $entryExit) {
                $action = $entryExit['action'] === 'entry' ? 'entered' : 'exited';
                $events[] = "💡 {$entryExit['fund']} {$action} a position in {$stock['symbol']}.";
            }
        }**/

        return $events;
    }

    /**
     * Generate corporate events and filings-related events based on stock data.
     *
     * @param array $stock The stock data.
     * @return array The list of generated events.
     */
    private function generateCorporateEventsFillings(array $stock): array
    {
        $events = [];

        // New SEC filings (e.g., 8-Ks, 10-Ks)
        //if (!empty($stock['sec_filings'])) {
        //    foreach ($stock['sec_filings'] as $filing) {
        //        $events[] = "💡 {$stock['symbol']} filed a new {$filing['type']} on {$filing['date']} disclosing {$filing['description']}.";
        //    }
        //}

        // Dividend announcements or changes
        if (!empty($stock['avg_dividend'])) {
            $dividend = $stock['avg_dividend'];
            $events[] = "💡 {$stock['symbol']} announced a dividend of {$dividend} payable on {$dividend['paydate']}.";
        }

        // Stock splits or reverse splits
        //if (!empty($stock['stock_splits'])) {
        //    foreach ($stock['stock_splits'] as $split) {
         //       $type = $split['type'] === 'split' ? 'stock split' : 'reverse stock split';
        //        $events[] = "💡 {$stock['symbol']} announced a {$type} with a ratio of {$split['ratio']} effective on {$split['effective_date']}.";
        //    }
        //}

        return $events;
    }

    /**
     * Fetch stock data for a given symbol.
     * Replace this with actual API calls or database queries.
     *
     * @param string $symbol The stock symbol.
     * @return array The stock data.
     */
    private function getStockData(string $symbol): array
    {
        // Sub‐query: most recent insider rating change per stock
        $latestInsider = DB::table('stock_insiders as si1')
            ->select('si1.*')
            ->whereRaw('si1.id = (SELECT MAX(si2.id) FROM stock_insiders as si2 WHERE si2.stock_id = si1.stock_id)');
// Sub‐query: most recent institutional ownership per stock
        $latestInstitution = DB::table('stock_institutional_ownership as io1')
            ->select('io1.*')   
            ->whereRaw('io1.id = (SELECT MAX(io2.id) FROM stock_institutional_ownership as io2 WHERE io2.stock_id = io1.stock_id)');

$stock = DB::table('stock_symbols')
    // Percentage changes
    ->join('stock_percentage_daily as daily', function($j){
        $j->on('stock_symbols.id','=','daily.stock_id')
          ->whereRaw('daily.closing_date = (
              SELECT MAX(closing_date)
                FROM stock_percentage_daily
               WHERE stock_id = stock_symbols.id
          )');
    })
    ->join('stock_percentage_weekly as weekly', function($j){
        $j->on('stock_symbols.id','=','weekly.stock_id')
          ->whereRaw('weekly.closing_date = (
              SELECT MAX(closing_date)
                FROM stock_percentage_weekly
               WHERE stock_id = stock_symbols.id
          )');
    })
    ->join('stock_percentage_monthly as monthly', function($j){
        $j->on('stock_symbols.id','=','monthly.stock_id')
          ->whereRaw('monthly.closing_date = (
              SELECT MAX(closing_date)
                FROM stock_percentage_monthly
               WHERE stock_id = stock_symbols.id
          )');
    })

    // Latest candles
    ->join('stock_candle_daily as daily_candle', function($j){
        $j->on('stock_symbols.id','=','daily_candle.stock_id')
          ->whereRaw('daily_candle.ts = (
              SELECT MAX(ts)
                FROM stock_candle_daily
               WHERE stock_id = stock_symbols.id
          )');
    })
    ->join('stock_candle_weekly as weekly_candle', function($j){
        $j->on('stock_symbols.id','=','weekly_candle.stock_id')
          ->whereRaw('weekly_candle.ts = (
              SELECT MAX(ts)
                FROM stock_candle_weekly
               WHERE stock_id = stock_symbols.id
          )');
    })
    ->join('stock_candle_monthly as monthly_candle', function($j){
        $j->on('stock_symbols.id','=','monthly_candle.stock_id')
          ->whereRaw('monthly_candle.ts = (
              SELECT MAX(ts)
                FROM stock_candle_monthly
               WHERE stock_id = stock_symbols.id
          )');
    })

    // Insider and institutional latest rows
    ->leftJoinSub($latestInsider, 'insiders', function($j){
        $j->on('stock_symbols.id','=','insiders.stock_id');
    })
    ->leftJoinSub($latestInstitution, 'institution', function($j){
        $j->on('stock_symbols.id','=','institution.stock_id');
    })

    // Indicators and fundamentals
    ->join('stock_symbol_info','stock_symbols.id','=','stock_symbol_info.stock_id')
    ->join('stock_indicators','stock_symbols.id','=','stock_indicators.stock_id')
    ->join('stock_basic_financials_metric','stock_symbols.id','=','stock_basic_financials_metric.stock_id')
    ->join('stock_price_target','stock_symbols.id','=','stock_price_target.stock_id')

    // Social sentiment
    ->join(DB::raw('(
        SELECT sss1.*
        FROM stock_social_sentiments sss1
        WHERE sss1.id = (
            SELECT MAX(sss2.id)
            FROM stock_social_sentiments sss2
            WHERE sss2.stock_id = sss1.stock_id
        )
    ) as stock_social_sentiments'), 'stock_symbols.id', '=', 'stock_social_sentiments.stock_id')
     
    // Earnings calendar
    ->join(DB::raw('(
        SELECT sec1.*
        FROM stock_earnings_calendar sec1
        WHERE sec1.cal_date = (
            SELECT MAX(sec2.cal_date)
            FROM stock_earnings_calendar sec2
            WHERE sec2.stock_id = sec1.stock_id
        )
    ) as stock_earnings_calendar'), 'stock_symbols.id', '=', 'stock_earnings_calendar.stock_id')


    // Upgrade/downgrade table aliased to avoid unprefixed 'company'
    ->join(DB::raw('(
        SELECT sud1.*
        FROM stock_upgrade_downgrade sud1
        WHERE sud1.grade_time = (
            SELECT MAX(sud2.grade_time)
            FROM stock_upgrade_downgrade sud2
            WHERE sud2.stock_id = sud1.stock_id
        )
    ) as upgrade'), 'stock_symbols.id', '=', 'upgrade.stock_id')
    ->join(DB::raw('(
        SELECT *
        FROM stock_dividend_quarterly sdq1
        WHERE sdq1.id = (
            SELECT MAX(sdq2.id)
            FROM stock_dividend_quarterly sdq2
            WHERE sdq2.stock_id = sdq1.stock_id
        )
    ) as stock_dividend_quarterly'), 'stock_symbols.id', '=', 'stock_dividend_quarterly.stock_id')

    ->join(DB::raw('(
        SELECT seq1.*
        FROM stock_earnings_quality_quarterly seq1
        WHERE seq1.id = (
            SELECT MAX(seq2.id)
            FROM stock_earnings_quality_quarterly seq2
            WHERE seq2.stock_id = seq1.stock_id
        )
    ) as stock_earnings_quality_quarterly'), 'stock_symbols.id', '=', 'stock_earnings_quality_quarterly.stock_id')

    ->join(DB::raw('(
    SELECT sns1.*
    FROM stock_news_sentiments sns1
    WHERE sns1.id = (
        SELECT MAX(sns2.id)
        FROM stock_news_sentiments sns2
        WHERE sns2.stock_id = sns1.stock_id
    )
) as stock_news_sentiments'), 'stock_symbols.id', '=', 'stock_news_sentiments.stock_id')

    ->where('stock_symbols.symbol',$symbol)
    ->select(
        'stock_symbols.id as stock_id',
        'stock_symbols.symbol',
        'daily.percentage as daily_change_percentage',
        'weekly.percentage as weekly_change_percentage',
        'monthly.percentage as monthly_change_percentage',

        'stock_indicators.rsi',
        'stock_indicators.ema_50',

        'daily_candle.close_price as current_price',
        'daily_candle.volume',

        'stock_basic_financials_metric.ten_day_ave_trading_vol as average_volume',
        'stock_basic_financials_metric.fifty_two_week_high',
        'stock_basic_financials_metric.fifty_two_week_low',

        'stock_earnings_calendar.cal_date as earnings_date',
        'stock_earnings_calendar.eps_actual as actual_eps',
        'stock_earnings_calendar.eps_estimate as expected_eps',

        'stock_earnings_quality_quarterly.score as earnings_quality_score',
        'stock_earnings_quality_quarterly.prev_score as previous_quality_score',

        'stock_news_sentiments.companynews_score as news_sentiment',
        'stock_news_sentiments.prev_companynews_score as previous_news_sentiment',

        'stock_social_sentiments.mentions as social_mentions',

        'stock_price_target.target_median as latest_target_price',
        'stock_price_target.prev_target_median as previous_target_price',

        // upgrade/downgrade fields (prefixed)
        'upgrade.company as upgrade_company',
        'upgrade.from_grade',
        'upgrade.to_grade',
        'upgrade.action',
        'upgrade.grade_time',

        // average mentions
        DB::raw('AVG(stock_social_sentiments.mentions) as average_social_mentions')
    )
    ->groupBy(
        'stock_symbols.id',
        'stock_symbols.symbol',
        'daily.percentage',
        'weekly.percentage',
        'monthly.percentage',
        'stock_indicators.rsi',
        'stock_indicators.ema_50',
        'daily_candle.close_price',
        'daily_candle.volume',
        'stock_basic_financials_metric.ten_day_ave_trading_vol',
        'stock_basic_financials_metric.fifty_two_week_high',
        'stock_basic_financials_metric.fifty_two_week_low',
        'stock_earnings_calendar.cal_date',
        'stock_earnings_calendar.eps_actual',
        'stock_earnings_calendar.eps_estimate',
        'stock_earnings_quality_quarterly.score',
        'stock_earnings_quality_quarterly.prev_score',
        'stock_news_sentiments.companynews_score',
        'stock_news_sentiments.prev_companynews_score',
        'stock_social_sentiments.mentions',
        'stock_price_target.target_median',
        'stock_price_target.prev_target_median',
        // include the aliased upgrade columns
        'upgrade.company',
        'upgrade.from_grade',
        'upgrade.to_grade',
        'upgrade.action',
        'upgrade.grade_time'
    )
    ->first();

        // Return the stock data as an array or an empty array if no data is found
        return $stock ? (array) $stock : [];
    }

    /**
     * Generate a log event if there is a latest article for the stock.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateLogIfLatestArticle(Request $request)
    {
        $stock_symbol = $request->input('stock_symbol');
        $article_url = $request->input('article_url');
        $article_title = $request->input('article_title');
        
        // Get the stock_id for the given stock_symbol
        $stock = DB::table('stock_symbols')->where('symbol', $stock_symbol)->first();

        if (!$stock || empty($article_url) || empty($article_title)) {
            return response()->json(['error' => 'Invalid parameters or stock not found.'], 400);
        }

        $message = "{$stock_symbol} has published a new article: \"{$article_title}\". Read more at: {$article_url}";

        if (StockEventLogger::logEvent($stock->id, $message)) {
            return response()->json(['message' => 'Stock events logged successfully']);
        } else {
            return response()->json(['error' => 'Event already logged or failed to log.'], 400);
        }
    }


    public function getStockEvents(Request $request)
    {
        $request->validate([
            'stock_id' => 'required|integer|exists:stock_symbols,id',
        ]);

        $stockId = $request->input('stock_id');

        $events = DB::table('stock_event_log')
            ->where('stock_id', $stockId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'stock_id' => $stockId,
            'events' => $events
        ]);
    }
}