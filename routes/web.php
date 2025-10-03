<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request; // add this
use Carbon\Carbon;           // add this
use Illuminate\Support\Facades\Http;        // added
use Illuminate\Http\Client\Pool;            // added

/**
 * Controllers
 */

// v1 Controllers
use App\Http\Controllers\v1\BasicFinancialController;
use App\Http\Controllers\v1\CompanyNewsController;
use App\Http\Controllers\v1\IndicatorController;
use App\Http\Controllers\v1\IndicatorHistoricalController;
use App\Http\Controllers\v1\InstitutionalOwnershipController;
use App\Http\Controllers\v1\InstitutionalPortfolioController;
use App\Http\Controllers\v1\PriceMetricController;
use App\Http\Controllers\v1\PriceTargetController;
use App\Http\Controllers\v1\StockArticleReference;
use App\Http\Controllers\v1\StockCandleController;
use App\Http\Controllers\v1\StockCompanyEarningsQualityScoreController;
use App\Http\Controllers\v1\StockDividendQuarterlyController;
use App\Http\Controllers\v1\StockEconomicController;
use App\Http\Controllers\v1\StockEarningsCalendarController;
use App\Http\Controllers\v1\StockInsiderController;
use App\Http\Controllers\v1\StockInvestmentReportController;
use App\Http\Controllers\v1\StockPricePredictionController;
use App\Http\Controllers\v1\StockQuoteController;
use App\Http\Controllers\v1\StockRecommendationController;
use App\Http\Controllers\v1\StockSectorMetricsController;
use App\Http\Controllers\v1\StockSentimentController;
use App\Http\Controllers\v1\StockSocialSentimentController;
use App\Http\Controllers\v1\StockSplitsController;
use App\Http\Controllers\v1\StockTradingScoreController;
use App\Http\Controllers\v1\StockUpgradeDowngradeController;
use App\Http\Controllers\v1\StockAnalysisQAController;
use App\Http\Controllers\v1\StockCompanyPeersController;

// User / System Controllers
use App\Http\Controllers\system\StockEventLoggerController;
use App\Http\Controllers\user\UserController;
use App\Http\Controllers\user\UserGeneratorController;
use App\Http\Controllers\user\UserPortfoliosController;
use App\Http\Controllers\user\UserQuestionnaireController;
use App\Http\Controllers\user\UserSocialPost;

// System Controllers
use App\Http\Controllers\system\SequentialCronController;

// Endpoint Controllers
use App\Http\Controllers\endpoints\v1\BasicFinancialController as EndpointBasicFinancial;
use App\Http\Controllers\endpoints\v1\InstitutionalOwnershipController as EndpointInstitutionalOwnership;
use App\Http\Controllers\endpoints\v1\QuoteController as EndpointQuote;
use App\Http\Controllers\endpoints\v1\StockBasicFinancialMetricController as EndpointStockBasicFinancialMetric;
use App\Http\Controllers\endpoints\v1\StockCandleDailyController as EndpointStockCandleDaily;
use App\Http\Controllers\endpoints\v1\StockCompanyNewsController as EndpointStockCompanyNews;
use App\Http\Controllers\endpoints\v1\StockEconomicCalendarController as EndpointStockEconomicCalendar;
use App\Http\Controllers\endpoints\v1\StockEarningsCalendarController as EndpointStockEarningsCalendar;
use App\Http\Controllers\endpoints\v1\StockInsiderController as EndpointStockInsider;
use App\Http\Controllers\endpoints\v1\StockKnowledgeBaseController;
use App\Http\Controllers\endpoints\v1\StockNewsSentimentController as EndpointStockNewsSentiment;
use App\Http\Controllers\endpoints\v1\StockPriceTargetController as EndpointStockPriceTarget;
use App\Http\Controllers\endpoints\v1\StockRecommendationController as EndpointStockRecommendation;
use App\Http\Controllers\endpoints\v1\StockScreenerController;
use App\Http\Controllers\endpoints\v1\StockUnlimitedScreenerController;
use App\Http\Controllers\endpoints\v1\StockUpgradeDowngradeController as EndpointStockUpgradeDowngrade;
use App\Http\Controllers\endpoints\v1\StockUserChatbotQA;

// Indicators (namespace \App\Http\Controllers\Indicators)
use App\Http\Controllers\Indicators\AdxController;
use App\Http\Controllers\Indicators\Ema50Controller;
use App\Http\Controllers\Indicators\LowerbController;
use App\Http\Controllers\Indicators\MacdController;
use App\Http\Controllers\Indicators\MacdSignalLineController;
use App\Http\Controllers\Indicators\MinusdiController;
use App\Http\Controllers\Indicators\ObvController;
use App\Http\Controllers\Indicators\PlusdiController;
use App\Http\Controllers\Indicators\PriceController;
use App\Http\Controllers\Indicators\RsiController;
use App\Http\Controllers\Indicators\Sma50Controller;
use App\Http\Controllers\Indicators\UpperbController;
use App\Http\Controllers\Indicators\BootstrapController;
use App\Http\Controllers\Indicators\LatestController as LatestIndicatorController;

/**
 * Home
 */


/**
 * Stock Data & Metrics
 */
Route::prefix('v1')->group(function () {
    Route::get('/get-stock-data', [StockArticleReference::class, 'getAllReferencesFixSpeed']);
    Route::get('/get-stock-metrics', [StockArticleReference::class, 'getStockMetrics']);
    Route::get('/get-stock-metrics-single/{symbol}', [StockArticleReference::class, 'getStockMetricsSingle']);
    Route::get('/get-top-100-stocks-by-market-cap', [StockArticleReference::class, 'getAllTop100StocksByMarketCap']);

    Route::get('/reference', [StockArticleReference::class, 'getAllReferences']);
    Route::get('/referencev2', [StockArticleReference::class, 'getAllReferencesV2']);
    Route::get('/referencev3', [StockArticleReference::class, 'getAllReferencesV3']);

    Route::get('/get-news-by-market-cap-desc', [StockArticleReference::class, 'getAllNewsLimit']);
    Route::get('/sector_news', [StockArticleReference::class, 'getNewsBySector']);
    Route::get('/all_news', [StockArticleReference::class, 'getAllNews']);
    Route::get('/all_small_cap_news', [StockArticleReference::class, 'getAllNewsSmallCap']);
    Route::get('/all_mid_cap_news', [StockArticleReference::class, 'getAllNewsMidCap']);
});

/**
 * Analyst Reports
 */
Route::get('/v1/analyst-reports', [StockInvestmentReportController::class, 'getAnalystReports']);
Route::get('/v1/analyst-reports-single/{symbol}', [StockInvestmentReportController::class, 'getAnalystReportsSingle']);
/**
 * User Management
 */
Route::prefix('v1')->group(function () {
    Route::get('/user/activate', [UserController::class, 'activate']);
    Route::post('/user/resend-verification', [UserController::class, 'resendVerificationLink']);
});
Route::get('/sendEmail', [UserController::class, 'sendWelcomeEmail']);

/**
 * User Portfolios
 */
Route::prefix('v1')->group(function () {
    // need to put this in the CRON JOBS
    Route::get('/watch-current-price-market-cap', [UserPortfoliosController::class, 'updateDailyPLAndMarketCap']);
    Route::get('/log-daily-portfolio-total-value', [UserPortfoliosController::class, 'logDailyPortfolioTotalValue']);
    Route::get('/get-all-daily-pl-unrealized-pl/{user_id}', [UserPortfoliosController::class, 'calculateUserPL']);
    Route::get('/get-stocks-by-portfolio/{user_id}/{portfolio_id}', [UserPortfoliosController::class, 'getStocksByPortfolio']);
    Route::get('/get-user-portfolios/{user_id}', [UserPortfoliosController::class, 'getUserPortfolios']);
    Route::get('/user/portfolios/details/{user_id}', [UserPortfoliosController::class, 'getUserPortfoliosDetails']);
    Route::get('/user-portfolio-logs', [UserPortfoliosController::class, 'getUserPortfolioLogs']);
    Route::get('/get-current-price-market-cap/{symbol}', [UserPortfoliosController::class, 'getCurrentPriceAndMarketCap']);
});

/**
 * Stock Event Logger
 */
Route::prefix('v1')->group(function () {
    Route::get('/log-stock-events', [StockEventLoggerController::class, 'logStockEvent']);
    Route::get('/get-stock-events', [StockEventLoggerController::class, 'getStockEvents']);
});

/**
 * Trading Score & Scoring
 */
Route::get('/v1/scoring', function () {
    $scores = DB::table('stock_trading_score')
        ->join('stock_symbols', 'stock_trading_score.symbol', '=', 'stock_symbols.symbol')
        ->join('stocks_by_market_cap', 'stock_trading_score.symbol', '=', 'stocks_by_market_cap.symbol')
        ->where('stocks_by_market_cap.notpriority', 0)
        ->join(DB::raw('(
            SELECT stock_id, close_price, ts
            FROM (
                SELECT stock_id, close_price, ts,
                       ROW_NUMBER() OVER (PARTITION BY stock_id ORDER BY ts DESC) as rn
                FROM stock_candle_daily
            ) t WHERE t.rn = 1
        ) as latest_candle'), 'stock_symbols.id', '=', 'latest_candle.stock_id')
        ->join(DB::raw('(
            SELECT stock_id, percentage, closing_date
            FROM (
                SELECT stock_id, percentage, closing_date,
                       ROW_NUMBER() OVER (PARTITION BY stock_id ORDER BY closing_date DESC) as rn
                FROM stock_percentage_daily
            ) t WHERE t.rn = 1
        ) as latest_percentage'), 'stock_symbols.id', '=', 'latest_percentage.stock_id')
        ->select('stock_trading_score.*', 'latest_candle.close_price', 'latest_percentage.percentage')
        ->limit(2000)
        ->get();

    return response()->json($scores);
});

Route::get('/v1/fundamental-percentage', [StockTradingScoreController::class, 'getFundamentalPercentage']);
Route::get('/v1/fundamental-percentage/{symbol}', [StockTradingScoreController::class, 'getFundamentalPercentageSingle']);
Route::get('/v1/stocks_scoring', [StockTradingScoreController::class, 'processStockTradingScore']);

Route::middleware('\App\Http\Middleware\UserApiToken')->group(function () {
    Route::get('/v1/scoring/{symbol}', function ($symbol) {
        $score = DB::table('stock_trading_score')->where('symbol', strtoupper($symbol))->first();
        if (!$score) {
            return response()->json(['error' => 'Score not found for this symbol'], 404);
        }
        return response()->json($score);
    });
});

/**
 * Dividends
 */
Route::get('/v1/pull_stock_dividend_batch', [StockDividendQuarterlyController::class, 'getStockDividendQuarterly']);
Route::get('/v1/dividends', fn () => response()->json(DB::table('stock_dividend_quarterly')->get()->toArray()));
Route::get('/v1/dividends/{symbol}', function ($symbol) {
    $stock = DB::table('stock_symbols')->where('symbol', strtoupper($symbol))->first();
    if (!$stock) {
        return response()->json(['error' => 'Stock not found'], 404);
    }
    $dividends = DB::table('stock_dividend_quarterly')->where('stock_id', $stock->id)->first();
    if (!$dividends) {
        return response()->json(['error' => 'No dividend data found for this stock'], 404);
    }
    return response()->json($dividends);
});

/**
 * Batch API Calls (pullers)
 */
Route::prefix('v1')->group(function () {
    Route::get('/pull_stock_info_from_api_batch', [\App\Http\Controllers\v1\StockController::class, 'getStockSymbolProfilesBatch']);
    Route::get('/pull_stocks_basic_financial_metric_batch', [BasicFinancialController::class, 'getBasicFinancialMetricBatch']);
    Route::get('/pull_stocks_candlestick_daily_batch', [StockCandleController::class, 'getCandleStickDailyBatch']);
    Route::get('/pull_stocks_candlestick_weekly_batch', [StockCandleController::class, 'getCandleStickWeeklyBatch']);
    Route::get('/pull_stocks_candlestick_monthly_batch', [StockCandleController::class, 'getCandleStickMonthlyBatch']);
    Route::get('/pull_stock_company_news_batch', [CompanyNewsController::class, 'getCompanyNewsBatch']);
    Route::get('/pull_stock_price_metric_batch', [PriceMetricController::class, 'getPriceMetricBatch']);
    Route::get('/pull_stock_price_target_batch', [PriceTargetController::class, 'getPriceTargetBatch']);
    Route::get('/pull_stock_insider_transaction_batch', [StockInsiderController::class, 'getStockInsiderBatch']);
    Route::get('/pull_stock_quote_batch', [StockQuoteController::class, 'getStockQuoteBatch']);
    Route::get('/pull_stock_news_sentiments_batch', [StockSentimentController::class, 'getSentimentsBatch']);
    Route::get('/pull_stock_market_cap_batch', [\App\Http\Controllers\v1\StockController::class, 'getStockMarketCapBatch']);
    Route::get('/pull_stock_social_sentiments_batch', [StockSocialSentimentController::class, 'getSocialSentimentsBatch']);
    Route::get('/pull_stock_upgrade_downgrade_batch', [StockUpgradeDowngradeController::class, 'getStockUpgradeDowngradeBatch']);
    Route::get('/pull_stock_earnings_calendar_batch', [StockEarningsCalendarController::class, 'getStockEarningsCalendarBatch']);
    Route::get('/pull_stock_economic_calendar_batch', [StockEconomicController::class, 'getStockEconomicCalendarBatch']);
    Route::get('/pull_stock_institutional_ownership_batch', [InstitutionalOwnershipController::class, 'getInstitutionalOwnershipBatch']);
    Route::get('/pull_stock_institutional_portfolio_batch', [InstitutionalPortfolioController::class, 'getInstitutionalPortfolioBatch']);
    Route::get('/pull_stock_market_cap_bulk', [\App\Http\Controllers\v1\StockController::class, 'getStockMarketCapBulk']);
    Route::get('/update_top_2000_stocks_by_market_cap', [\App\Http\Controllers\v1\StockController::class, 'updateTop2000StocksByMarketCap']);
    Route::get('/pull_stock_indicators_batch/{indicator}', [IndicatorController::class, 'pullStockIndicatorsBatch']);
    Route::get('/pull_stock_recommendation_batch', [StockRecommendationController::class, 'getStockRecommendationBatch']);
    Route::get('/pull_stock_earnings_quality_quarterly_batch', [StockEarningsQualityQuarterlyController::class, 'getEarningsQualityQuarterlyBatch']);
    
    //Route::get('/pull_stock_historical_indicators_batch/{indicator}', [IndicatorHistoricalController::class, 'upsertIndicator']);
});

/**
 * Endpoint APIs (protected)
 */
Route::get('/v1/get_earnings_calendar_all', [EndpointStockEarningsCalendar::class, 'getAllEarningsCalendar']);

Route::get('/v1/ssscreener', [StockUnlimitedScreenerController::class, 'getScreenerResult']);

//Route::middleware('\App\Http\Middleware\UserApiToken')->group(function () {
    Route::get('/v1/screener', [StockScreenerController::class, 'getScreenerResult']);
    Route::get('/v1/sscreener', [StockUnlimitedScreenerController::class, 'getScreenerResult']);

    Route::get('/v1/get_quote/{symbol}', [EndpointQuote::class, 'getAllStockQuotes']);
    Route::get('/v1/get_basic_financial/{symbol}', [EndpointBasicFinancial::class, 'getAllBasicFinancialMetrics']);
    Route::get('/v1/get_basic_financial_metric/{symbol}', [EndpointStockBasicFinancialMetric::class, 'getAllBasicFinancialMetrics']);
    Route::get('/v1/get_price_target/{symbol}', [EndpointStockPriceTarget::class, 'getPriceTargetBySymbol']);
    Route::get('/v1/get_candlesticks_daily/{symbol}', [EndpointStockCandleDaily::class, 'getAllStockCandleDaily']);
    Route::get('/v1/get_economic_calendar_single/{symbol}', [EndpointStockEconomicCalendar::class, 'getAllEconomicCalendar']);
    Route::get('/v1/get_economic_calendar_all', [EndpointStockEconomicCalendar::class, 'getAllEconomicCalendar']);
    Route::get('/v1/get_stock_social_sentiment_all', [\App\Http\Controllers\endpoints\v1\StockSocialSentimentController::class, 'getAllStockSocialSentiments']);
    Route::get('/v1/get_stock_social_sentiment_single/{symbol}', [\App\Http\Controllers\endpoints\v1\StockSocialSentimentController::class, 'getSocialSentimentBySymbol']);
    Route::get('/v1/get_earnings_calendar_single/{symbol}', [EndpointStockEarningsCalendar::class, 'getAllEarningsCalendarBySymbol']);
    Route::get('/v1/get_earnings_calendar_marketcap_limit', [EndpointStockEarningsCalendar::class, 'getAllEarningsCalendarMarketCapLimit']);
    Route::get('/v1/get_stock_recommendation_single/{symbol}', [EndpointStockRecommendation::class, 'getStockRecommendationBySymbol']);
    Route::get('/v1/get_stock_recommendation_all', [EndpointStockRecommendation::class, 'getAllStockRecommendations']);
    Route::get('/v1/get_upgrade_downgrade_single/{symbol}', [EndpointStockUpgradeDowngrade::class, 'getUpgradeDowngradeBySymbol']);
    Route::get('/v1/get_upgrade_downgrade_all', [EndpointStockUpgradeDowngrade::class, 'getAllUpgradeDowngrades']);
    Route::get('/v1/get_institutional_ownership_single/{symbol}', [EndpointInstitutionalOwnership::class, 'getInstitutionalOwnershipBySymbol']);
    Route::get('/v1/get_institutional_ownership_all', [EndpointInstitutionalOwnership::class, 'getAllInstitutionalOwnership']);
    Route::get('/v1/get_insider_single/{symbol}', [EndpointStockInsider::class, 'getInsiderBySymbol']);
    Route::get('/v1/get_insider_all', [EndpointStockInsider::class, 'getAllInsiders']);
    
    Route::get('/v1/get_news_sentiment_single/{symbol}', [EndpointStockNewsSentiment::class, 'getNewsSentimentBySymbol']);
    Route::get('/v1/get_news_sentiment_all', [EndpointStockNewsSentiment::class, 'getAllNewsSentiments']);
    Route::get('/v1/get_company_news_single/{symbol}', [EndpointStockCompanyNews::class, 'getCompanyNewsBySymbol']);
    Route::get('/v1/get_company_news_all', [EndpointStockCompanyNews::class, 'getAllCompanyNews']);
//});

/**
 * Protected routes (misc)
 */
//Route::middleware('\App\Http\Middleware\UserApiToken')->group(function () {
    Route::get('/v1/get-top-100-stocks-by-market-cap-v2', [StockArticleReference::class, 'getAllTop100StocksByMarketCap']);
    
    // Get all stock symbols from stocks_by_market_cap table
    Route::get('/v1/get-stock-symbols', function () {
        try {
            $symbols = DB::table('stocks_by_market_cap')
                ->select('symbol')
                ->orderBy('id', 'asc')
                ->pluck('symbol')
                ->toArray();
            
            return response()->json($symbols);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Error retrieving stock symbols: ' . $e->getMessage()
            ], 500);
        }
    });
//});

/**
 * Questionnaire (protected)
 */
//Route::middleware('\App\Http\Middleware\UserApiToken')->group(function () {
    Route::get('/v1/questionnaire/questions-only', [UserQuestionnaireController::class, 'getQuestions']);
    Route::get('/v1/questionnaire/choices', [UserQuestionnaireController::class, 'getChoices']);
    Route::get('/v1/questionnaire/profile-tags', [UserQuestionnaireController::class, 'getProfileTags']);
    Route::get('/v1/questionnaire/user-suggested-tag', [UserQuestionnaireController::class, 'getUserSuggestedTag']);
    Route::get('/v1/questionnaire/user-profile-tags', [UserQuestionnaireController::class, 'getUserInvestmentPTags']);
//});

/**
 * Chatbot QA & Knowledge Base
 */
Route::get('/v1/get-chatbot-qa', [StockUserChatbotQA::class, 'getAllChatbotQA']);
Route::get('/v1/get-portfolio-analyzer-qa', [StockUserChatbotQA::class, 'getAllPortfolioAnalyzerQA']);
Route::get('/v1/user/stock-queries', [StockAnalysisQAController::class, 'getAllUserStockQueries']);
Route::get('/v1/knowledge-base', [StockKnowledgeBaseController::class, 'getKnowledgeBaseContent']);

/**
 * Social / User
 */
Route::get('/v1/user/external-subscription', [UserController::class, 'getExternalSubscription']);
Route::get('/v1/social-posts/all', [UserSocialPost::class, 'getAllPosts']);
Route::get('/v1/social-posts/comments', [UserSocialPost::class, 'getComments']);
Route::get('/v1/user/followers', [UserSocialPost::class, 'getFollowers']);
Route::get('/v1/user/likers', [UserSocialPost::class, 'getLikers']);
Route::get('/v1/user/sharers', [UserSocialPost::class, 'getSharers']);
Route::get('/v1/social-posts/search', [UserSocialPost::class, 'searchPosts'])->name('social-posts.search');
Route::get('/v1/user/following', [UserSocialPost::class, 'getFollowing'])->name('user.following');
Route::get('/v1/user/liked-posts', [UserSocialPost::class, 'getLikedPosts'])->name('user.liked_posts');


/**
 * Candles / Price Prediction / Peers
 */
Route::get('/v1/stocks/{symbol}/candles/range', [EndpointStockCandleDaily::class, 'getBySymbolAndDateRange']);
Route::get('/v1/show-price-prediction', [StockCandleController::class, 'displayPricePrediction']);
Route::get('/v1/get-stock-price-prediction', [StockPricePredictionController::class, 'fetch']);
Route::get('/v1/stock-price-prediction-single/{symbol}', [StockPricePredictionController::class, 'fetchSingle']);

Route::get('/v1/peers-scores/{symbol}', function ($symbol) {
    $results = DB::table('stock_company_peers')
        ->join('stock_symbols', 'stock_company_peers.stock_id', '=', 'stock_symbols.id')
        ->join('stock_trading_score', 'stock_company_peers.peer_symbol', '=', 'stock_trading_score.symbol')
        ->where('stock_symbols.priority', 1)
        ->where('stock_symbols.symbol', strtoupper($symbol))
        ->select('stock_company_peers.peer_symbol', 'stock_trading_score.trade_engine_score')
        ->get();

    return response()->json($results);
});

Route::prefix('v1')->group(function () {
    Route::get('/update-actual-price-daily', [StockPricePredictionController::class, 'updateActualPriceDaily']);
    Route::get('/update-actual-price-weekly', [StockPricePredictionController::class, 'updateActualPriceWeekly']);
    Route::get('/update-actual-price-monthly', [StockPricePredictionController::class, 'updateActualPriceMonthly']);
});

/**
 * News
 */
Route::get('/v1/fetch-top-news', [CompanyNewsController::class, 'fetchAndSaveTopNews']);
Route::get('/api/top-news', [CompanyNewsController::class, 'getTopNews']);
Route::get('/v1/news-analyst', [CompanyNewsController::class, 'getCompanyNewsList']);
Route::get('/v1/social-media-analyst', [StockSocialSentimentController::class, 'getSocialSentimentList']);
Route::get('/v1/fetch-insider-sentiment', [\App\Http\Controllers\v1\StockInsiderController::class, 'fetchAndSaveInsiderSentiment']);
Route::get('/v1/insider-sentiment', [\App\Http\Controllers\v1\StockInsiderController::class, 'getInsiderSentimentList']);

/**
 * Stock Splits
 */
Route::get('/v1/cron/fetch-splits', [StockSplitsController::class, 'fetchAndStore']);
Route::get('/v1/stock-splits/adjust-latest-candle', [StockSplitsController::class, 'adjustLatestCandleForSplit']);
// Expose Finnhub candle helper (accepts symbol, from (YYYY-MM-DD or epoch), to (YYYY-MM-DD or epoch))
Route::get('/v1/stock/candle', [StockSplitsController::class, 'getCandle']);

/**
 * Company Peers
 */
Route::get('/v1/stock-peers/fetch-and-save', [StockCompanyPeersController::class, 'fetchAndSaveStockPeers']);

/**
 * Indicators
 */
Route::prefix('v1/indicator')->group(function () {
    Route::get('/adx', [AdxController::class, 'saveToDb']);
    Route::get('/ema50', [Ema50Controller::class, 'saveToDb']);
    Route::get('/sma50', [Sma50Controller::class, 'saveToDb']);
    Route::get('/macd', [MacdController::class, 'saveToDb']);
    Route::get('/macdSignal', [MacdSignalLineController::class, 'saveToDb']);
    Route::get('/rsi', [RsiController::class, 'saveToDb']);
    Route::get('/plusdi', [PlusdiController::class, 'saveToDb']);
    Route::get('/minusdi', [MinusdiController::class, 'saveToDb']);
    Route::get('/obv', [ObvController::class, 'saveToDb']);
    Route::get('/lowerband', [LowerbController::class, 'saveToDb']);
    Route::get('/upperband', [UpperbController::class, 'saveToDb']);
    Route::get('/price', [PriceController::class, 'saveToDb']);
});

Route::get('/v1/bootstrap/indicators', [BootstrapController::class, 'allIndicators']);
Route::get('/v1/latest-indicators', [LatestIndicatorController::class, 'fetchIndicatorData']);
Route::get('/v1/stock-indicators/{symbol}', [LatestIndicatorController::class, 'getStockIndicators']);

/**
 * Diagnostics / Utilities
 */
Route::get('/v1/stocks/negative-market-cap', function () {
    $stocks = DB::table('stock_symbol_info')
        ->join('stock_symbols', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')
        ->where('stock_symbol_info.market_cap', '<', 0)
        ->select('stock_symbol_info.*', 'stock_symbols.symbol as stock_name')
        ->get();

});

/**
 * ──────────────────────────────────────────────────────────────────────────────
 * Artisan-trigger endpoints (moved to bottom)
 * ──────────────────────────────────────────────────────────────────────────────
 */

Route::get('/v1/trigger-cron_fundamentals', function () {
    Artisan::call('stocks:fundamentals');
    return response('Stocks process fundamentals triggered', 200);
});

Route::get('/v1/trigger-cron_insider-sentiment', function () {
    Artisan::call('stocks:insider_sentiment');
    return response('Stocks process insider_sentiment triggered', 200);
});


// Separate explicit artisan endpoint for only the fundamentals command
Route::get('/v1/trigger-fundamentals-only', function () {
    Artisan::call('stocks:fundamentals');
    return response('Stocks fundamentals (separate endpoint) triggered', 200);
});

Route::get('/v1/trigger-cron_fundamentals-p2', function () {
    Artisan::call('stocks:fundamentals_p2');
    return response('Stocks process fundamentals triggered', 200);
});
Route::get('/v1/trigger-cron_indicators-batch1', function () {
    Artisan::call('stocks:indicator_non_historical_p1');
    return response('Stocks indicators batch 1 triggered', 200);
});
Route::get('/v1/trigger-cron_indicators-batch2', function () {
    Artisan::call('stocks:indicator_non_historical_p2');
    return response('Stocks indicators batch 2 triggered', 200);
});
Route::get('/v1/trigger-cron_trading-score', function () {
    Artisan::call('stocks:trading_score');
    return response('Stocks trading score triggered', 200);
});
Route::get('/v1/trigger-cron_historical-indicators', function () {
    Artisan::call('stocks:indicator_historical');
    return response('Stocks historical indicators triggered', 200);
});
Route::get('/v1/trigger-cron_candles', function () {
    Artisan::call('stocks:candles');
    return response('Stocks candles triggered', 200);
});
Route::get('/v1/trigger-cron_earnings-calendar', function () {
    // call to the command
    Artisan::call('stocks:earnings_calendar');
    return response('Stocks earnings calendar triggered', 200);
});

Route::get('/v1/trigger-cron_company-news', function () {
    Artisan::call('stocks:company_news');
    return response('Stocks company news triggered', 200);
});

Route::get('/v1/trigger-candles-weekly', function () {
    //if (request('token') !== env('STOCKS_BATCH_TOKEN')) abort(403, 'Unauthorized');
    Artisan::call('stocks:candles_weekly');
    return response('Stocks candles weekly triggered', 200);
});
Route::get('/v1/trigger-candles-monthly', function () {
   // if (request('token') !== env('STOCKS_BATCH_TOKEN')) abort(403, 'Unauthorized');
    Artisan::call('stocks:candles_monthly');
    return response('Stocks candles monthly triggered', 200);
});
// Trigger backfill of missing daily stock candles (protected by token)
Route::get('/v1/trigger-fill-missing-daily', function () {
   // if (request('token') !== env('STOCKS_BATCH_TOKEN')) abort(403, 'Unauthorized');
    // Call the controller action and pass the current request (it will use defaults when params absent)
    return app(\App\Http\Controllers\v1\StockMissingDataController::class)->fillMissingDailyData(request());
    //echo 24;
});
Route::get('/v1/trigger-fill-missing-weekly', function () {
  //  if (request('token') !== env('STOCKS_BATCH_TOKEN')) abort(403, 'Unauthorized');
    // Call the controller action and pass the current request (it will use defaults when params absent)
    return app(\App\Http\Controllers\v1\StockMissingDataController::class)->fillMissingWeeklyData(request());
    //echo 24;
});
Route::get('/v1/trigger-fill-missing-monthly', function () {
  //  if (request('token') !== env('STOCKS_BATCH_TOKEN')) abort(403, 'Unauthorized');
    // Call the controller action and pass the current request (it will use defaults when params absent)
    return app(\App\Http\Controllers\v1\StockMissingDataController::class)->fillMissingMonthlyData(request());
    //echo 24;
});
# old endpoints

// If you want the full path without /api prefix use Route::get('/v1/...') in web.php instead.
Route::get('/v1/stock-splits/retroactive-candles', [StockSplitsController::class, 'getRetroactiveCandles']);

Route::get('/v1/indicators/prices-scores', [BootstrapController::class, 'allIndicatorsWithScores']);
Route::get('/v1/indicators/prices', [BootstrapController::class, 'allIndicatorsPricesOnly']);

Route::get('/v1/earnings-calendar', [StockEarningsCalendarController::class, 'getStockEarningsCalendarBatch']);

use App\Http\Controllers\v1\StockPricePercentageController;
Route::get('/top-ten-latest-per-stock', [StockPricePercentageController::class, 'topTenLatestPerStock']);
Route::get('/worst-ten-latest-per-stock', [StockPricePercentageController::class, 'worstTenLatestPerStock']);

Route::get('/v1/insider-transactions', [\App\Http\Controllers\v1\StockInsiderController::class, 'getInsidersList']);

Route::get('/v1/financial-metrics/{symbol}', [PriceMetricController::class, 'getFinancialMetrics']);

/**
 * Stock Insiders
 */
Route::get('stock-insiders/yesterday', [StockInsiderController::class, 'getTodayStockInsiders']);
Route::get('stock-insiders/yesterday2', [StockInsiderController::class, 'getTodayStockInsiders2']);

Route::get('tzcheck',function(){
   
echo "PHP Default Timezone: " . date_default_timezone_get() . "<br>";
echo "Current Server Time: " . date("Y-m-d H:i:s T") . "<br>";
echo "Current UTC Time: " . gmdate("Y-m-d H:i:s T") . "<br>";

});

/**
 * ──────────────────────────────────────────────────────────────────────────────
 * Sequential Cron Job Management
 * ──────────────────────────────────────────────────────────────────────────────
 */
Route::prefix('v1')->group(function () {
    // Execute all cron jobs sequentially
    Route::get('/trigger-all-cron-sequential', [SequentialCronController::class, 'executeAllJobs']);
    
    // Get status of all cron jobs
    Route::get('/cron-jobs-status', [SequentialCronController::class, 'getJobsStatus']);
    
    // Reset all cron jobs to idle status
    Route::get('/cron-jobs-reset', [SequentialCronController::class, 'resetAllJobs']);
    
    // Execute a single job by name (for testing)
    Route::get('/trigger-single-cron/{jobName}', [SequentialCronController::class, 'executeSingleJob']);
});

// Cron Jobs Dashboard
Route::get('/cron-dashboard', function () {
    return view('cron-dashboard');
});


// Reworked combined trigger: lock (set flags to 1), run command, then reset flags back to 0
Route::get('/v1/trigger-all-batches', function () {
    $commands = [
        'stocks:fundamentals',
        'stocks:fundamentals_p2',
        'stocks:indicators_batch1',
        'stocks:candles',
        'stocks:historical-indicators',
        'stocks:earnings_calendar',
        'stocks:trading_score',
        'stocks:company_news',
    ];

    // Map each artisan command to its processed_* column
    $flagMap = [
        'stocks:fundamentals'          => 'processed_fundamentals',
        'stocks:fundamentals_p2'       => 'processed_fundamentals_p2',
        'stocks:indicators_batch1'     => 'processed_indicator_non_hist',
        'stocks:candles'               => 'processed_candles',
        //'stocks:historical-indicators' => 'processed_indicator',
        'stocks:earnings_calendar'     => 'processed_earnings_cal',
        'stocks:trading_score'         => 'processed_score',
        //'stocks:company_news'          => 'processed_company_news',
    ];

    $results = [];

    foreach ($commands as $cmd) {
        $start = microtime(true);
        $flagColumn = $flagMap[$cmd] ?? null;

        if ($flagColumn) {
            // Lock: set all rows that are 0 to 1 (to avoid other concurrent runs picking them)
            try {
                DB::table('stocks_by_market_cap')
                    ->where($flagColumn, 0)
                    ->update([$flagColumn => 1]);
            } catch (\Throwable $e) {
                // capture error but continue
            }
        }

        $exitCode = Artisan::call($cmd);
        $output   = trim(Artisan::output());

        if ($flagColumn) {
            // Reset: set all back to 0 so next run will process again
            try {
                DB::table('stocks_by_market_cap')->update([$flagColumn => 0]);
            } catch (\Throwable $e) {
                // capture error but continue
            }
        }

        $results[] = [
            'command'  => $cmd,
            'flag'     => $flagColumn,
            'exitCode' => $exitCode,
            'duration' => round(microtime(true) - $start, 2),
            'output'   => $output,
        ];
    }

    $html = '<h3>Triggered batch commands (locked -> processed -> reset)</h3><pre>' .
        htmlentities(json_encode($results, JSON_PRETTY_PRINT)) .
        '</pre>';

    return response($html, 200)->header('Content-Type', 'text/html');
});

// NEW: Scoring by stock and days_back
Route::get('/v1/scoring_by_stock', function (Request $request) {
    $symbol = strtoupper((string) $request->query('symbol', ''));
    if ($symbol === '') {
        return response()->json(['error' => 'symbol is required'], 422);
    }

    $daysBack = (int) $request->query('days_back', 30);
    $daysBack = max(1, min($daysBack, 365)); // clamp 1..365

    $from = Carbon::now()->subDays($daysBack);

    $scores = DB::table('stock_trading_score')
        ->join('stock_symbols', 'stock_trading_score.symbol', '=', 'stock_symbols.symbol')
        ->where('stock_trading_score.symbol', $symbol)
        ->where('stock_trading_score.date_updated', '>=', $from)
        // Latest close price (like /v1/scoring)
        ->leftJoin(DB::raw('(
            SELECT stock_id, close_price, ts
            FROM (
                SELECT stock_id, close_price, ts,
                       ROW_NUMBER() OVER (PARTITION BY stock_id ORDER BY ts DESC) as rn
                FROM stock_candle_daily
            ) t WHERE t.rn = 1
        ) as latest_candle'), 'stock_symbols.id', '=', 'latest_candle.stock_id')
        // Latest percentage (like /v1/scoring)
        ->leftJoin(DB::raw('(
            SELECT stock_id, percentage, closing_date
            FROM (
                SELECT stock_id, percentage, closing_date,
                       ROW_NUMBER() OVER (PARTITION BY stock_id ORDER BY closing_date DESC) as rn
                FROM stock_percentage_daily
            ) t WHERE t.rn = 1
        ) as latest_percentage'), 'stock_symbols.id', '=', 'latest_percentage.stock_id')
        ->select('stock_trading_score.*', 'latest_candle.close_price', 'latest_percentage.percentage')
        ->orderBy('stock_trading_score.date_updated', 'desc')
        ->get();

    return response()->json($scores);
});

// New: Stock Fundamentals Analysis - dedicated endpoint for fundamentals data
Route::get('/v1/stock-fundamentals/{symbol}', function (Request $request, string $symbol) {
    $symbol = strtoupper($request->route('symbol'));
    
    // Helper function to safely execute controller methods
    $safeCall = function ($callback, $fallback = null) {
        try {
            $result = $callback();
            // If it's a JsonResponse, get the data
            if ($result instanceof \Illuminate\Http\JsonResponse) {
                return $result->getData(true);
            }
            return $result ?: $fallback;
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => 'Controller call failed: ' . $e->getMessage(),
                'details' => substr($e->getTraceAsString(), 0, 200)
            ];
        }
    };

    // Create mock request objects for controllers that need them
    $mockRequest = new Request(['symbols' => $symbol]);
    $mockRequest->merge(['symbol' => $symbol]);

    $payload = [
        'fundamentals' => [
            'stock_metrics' => $safeCall(function () use ($mockRequest) {
                return \App\Http\Controllers\v1\StockArticleReference::getStockMetricsSingle($mockRequest);
            }),
            'basic_financial_metric' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\endpoints\v1\StockBasicFinancialMetricController::class)
                    ->getAllBasicFinancialMetrics($symbol);
            }),
            'fundamental_percentage' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\v1\StockTradingScoreController::class)
                    ->getFundamentalPercentageSingle($symbol);
            }),
        ],
        'meta' => [
            'symbol' => $symbol,
            'generated_at' => now()->toIso8601String(),
            'total_endpoints_called' => 3,
            'execution_method' => 'direct_controller_calls',
            'data_category' => 'fundamentals_only'
        ],
    ];

    return response()->json($payload, 200);
});

// New: Stock Technicals Analysis - dedicated endpoint for technicals data
Route::get('/v1/stock-technicals/{symbol}', function (Request $request, string $symbol) {
    $symbol = strtoupper($request->route('symbol'));
    
    // Helper function to safely execute controller methods
    $safeCall = function ($callback, $fallback = null) {
        try {
            $result = $callback();
            // If it's a JsonResponse, get the data
            if ($result instanceof \Illuminate\Http\JsonResponse) {
                return $result->getData(true);
            }
            return $result ?: $fallback;
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => 'Controller call failed: ' . $e->getMessage(),
                'details' => substr($e->getTraceAsString(), 0, 200)
            ];
        }
    };

    // Calculate 90 trading days cutoff (approximately 4.5 months considering weekends/holidays)
    $tradingDaysCutoff = Carbon::now()->subDays(130); // Using 130 calendar days to ensure 90 trading days

    $payload = [
        'technicals' => [
            'candlesticks_daily' => $safeCall(function () use ($symbol, $tradingDaysCutoff) {
                // Get stock_id for the symbol
                $stockId = DB::table('stock_symbols')->where('symbol', $symbol)->value('id');
                if (!$stockId) {
                    return ['error' => 'Stock symbol not found'];
                }

                // Get candlestick data limited to last 90 trading days
                $candles = DB::table('stock_candle_daily')
                    ->where('stock_id', $stockId)
                    ->where('ts', '>=', Carbon::now()->subDays(7)->format('Y-m-d')) // last 7 days
                    ->orderBy('ts', 'desc')
                    ->get();
                
                return $candles->toArray();
            }),
            'stock_indicators' => $safeCall(function () use ($symbol, $tradingDaysCutoff) {
                // Get stock_id for the symbol
                $stockId = DB::table('stock_symbols')->where('symbol', $symbol)->value('id');
                if (!$stockId) {
                    return ['error' => 'Stock symbol not found'];
                }
                
                // First, check what columns exist in stock_indicators table
                $columns = DB::getSchemaBuilder()->getColumnListing('stock_indicators');
                
                // Determine the correct date column to use
                $dateColumn = 'created_at'; // Default fallback
                if (in_array('date', $columns)) {
                    $dateColumn = 'date';
                } elseif (in_array('updated_at', $columns)) {
                    $dateColumn = 'updated_at';
                } elseif (in_array('created_at', $columns)) {
                    $dateColumn = 'created_at';
                } elseif (in_array('timestamp', $columns)) {
                    $dateColumn = 'timestamp';
                } elseif (in_array('ts', $columns)) {
                    $dateColumn = 'ts';
                }
                
                // Build the query with the correct date column
                $query = DB::table('stock_indicators')->where('stock_id', $stockId);
                
                // Get indicators data limited to last 90 trading days
                $indicators = $query->orderBy($dateColumn, 'desc')
                    ->limit(90) // Hard limit to 90 records
                    ->get();
                
                return [
                    'data' => $indicators->toArray(),
                    'meta' => [
                        'date_column_used' => $dateColumn,
                        'records_found' => $indicators->count()
                    ]
                ];
            }),
        ]
    ];

    return response()->json($payload, 200);
});
Route::get('/v1/stock-quant-risk/{symbol}', function (Request $request, string $symbol) {
    $symbol = strtoupper($request->route('symbol'));

    // --- Helper: safe controller call ---------------------------------------
    $safeCall = function ($callback, $fallback = null) {
        try {
            $result = $callback();

            // If it's a JsonResponse, get the underlying array
            if ($result instanceof \Illuminate\Http\JsonResponse) {
                return $result->getData(true);
            }

            // If it's a Laravel Collection, convert to array
            if ($result instanceof \Illuminate\Support\Collection) {
                return $result->toArray();
            }

            return $result ?: $fallback;
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => 'Controller call failed: ' . $e->getMessage(),
                'details' => substr($e->getTraceAsString(), 0, 200),
            ];
        }
    };

    // --- Helper: normalize a single row (array|object) -----------------------
    $rowToArray = function ($row) {
        if ($row instanceof \Illuminate\Database\Eloquent\Model) {
            return $row->toArray();
        }
        if ($row instanceof \stdClass) {
            return (array) $row;
        }
        return (array) $row;
    };

    // --- Generic filter with data-type aware rules ---------------------------
    $filterQuantRiskData = function ($data, $dataType = 'general') use ($rowToArray) {
        // Normalize containers
        if ($data instanceof \Illuminate\Support\Collection) $data = $data->toArray();
        if (is_object($data)) $data = (array) $data;

        // Whitelists per section
        $relevantFields = [
            'price_market_cap' => [
                'current_price','market_cap','shares_outstanding','float','beta',
                'pe_ratio','eps','dividend_yield','price_to_book','price_to_sales',
                'price_change','price_change_percent','volume','avg_volume'
            ],
            'price_target' => [
                'target_high','target_low','target_mean','target_median',
                'number_of_analysts','price_when_posted','currency'
            ],
            'price_prediction' => [
                // allow these and DO NOT drop the string fields
                'stock',                // string
                'date',                 // string/date
                'closing_price_today',  // numeric
            ],
        ];

        $fields = $relevantFields[$dataType] ?? $relevantFields['price_market_cap'];

        // Helper: keep strings for certain keys (not only numeric)
        $stringAllowed = ['stock','date','currency'];

        // If it's a list
        if (isset($data[0]) && (is_array($data[0]) || is_object($data[0]))) {
            $filtered = [];
            foreach ($data as $item) {
                $item = $rowToArray($item);
                $tmp = [];
                foreach ($fields as $field) {
                    if (array_key_exists($field, $item)) {
                        $val = $item[$field];
                        if (is_numeric($val) || in_array($field, $stringAllowed, true)) {
                            $tmp[$field] = $val;
                        }
                    }
                }
                // Normalize symbol/date
                $tmp['symbol'] = $tmp['stock'] ?? $item['symbol'] ?? $item['stock_symbol'] ?? null;

                // Date normalize to Y-m-d when possible
                $rawDate = $tmp['date'] ?? $item['date'] ?? $item['updated_at'] ?? $item['created_at'] ?? null;
                if ($rawDate) {
                    try { $tmp['date'] = Carbon::parse($rawDate)->toDateString(); } catch (\Throwable $e) { $tmp['date'] = $rawDate; }
                } else {
                    $tmp['date'] = null;
                }

                // Cast numeric
                if (isset($tmp['closing_price_today']) && $tmp['closing_price_today'] !== null && $tmp['closing_price_today'] !== '') {
                    $tmp['closing_price_today'] = (float) $tmp['closing_price_today'];
                }

                if (!empty($tmp)) $filtered[] = $tmp;
            }
            return $filtered;
        }

        // Single row
        $data = $rowToArray($data);
        $out = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $val = $data[$field];
                if (is_numeric($val) || in_array($field, $stringAllowed, true)) {
                    $out[$field] = $val;
                }
            }
        }

        // Normalize symbol
        $out['symbol'] = $out['stock'] ?? $data['symbol'] ?? $data['stock_symbol'] ?? null;

        // Normalize date -> Y-m-d
        $rawDate = $out['date'] ?? $data['date'] ?? $data['updated_at'] ?? $data['created_at'] ?? null;
        if ($rawDate) {
            try { $out['date'] = Carbon::parse($rawDate)->toDateString(); } catch (\Throwable $e) { $out['date'] = $rawDate; }
        } else {
            $out['date'] = null;
        }

        // Cast numeric
        if (isset($out['closing_price_today']) && $out['closing_price_today'] !== null && $out['closing_price_today'] !== '') {
            $out['closing_price_today'] = (float) $out['closing_price_today'];
        }

        return $out;
    };

    // --- Build payload -------------------------------------------------------
    $payload = [
        'quant_risk' => [
            'current_price_market_cap' => $safeCall(function () use ($symbol, $filterQuantRiskData) {
                $raw = app(\App\Http\Controllers\user\UserPortfoliosController::class)
                    ->getCurrentPriceAndMarketCap($symbol);
                if ($raw instanceof \Illuminate\Http\JsonResponse) $raw = $raw->getData(true);
                return $filterQuantRiskData($raw, 'price_market_cap');
            }),

            'price_target' => $safeCall(function () use ($symbol, $filterQuantRiskData) {
                $raw = app(\App\Http\Controllers\endpoints\v1\StockPriceTargetController::class)
                    ->getPriceTargetBySymbol($symbol);
                if ($raw instanceof \Illuminate\Http\JsonResponse) $raw = $raw->getData(true);
                return $filterQuantRiskData($raw, 'price_target');
            }),

            'price_prediction' => $safeCall(function () use ($symbol, $filterQuantRiskData) {
                $raw = app(\App\Http\Controllers\v1\StockPricePredictionController::class)
                    ->fetchSingle($symbol);

                if ($raw instanceof \Illuminate\Http\JsonResponse) {
                    $raw = $raw->getData(true);
                }

                // unwrap if nested under "data"
                if (isset($raw['predictions'])) {
                    $raw = $raw['predictions'];
                }

                return $filterQuantRiskData($raw, 'price_prediction');
            }),

            'basic_financial_metrics' => $safeCall(function () use ($symbol) {
    $stockId = DB::table('stock_symbols')->where('symbol', $symbol)->value('id');
    if (!$stockId) {
        return ['error' => 'Stock symbol not found'];
    }

    // use the correct table name
    $metrics = DB::table('stock_basic_financials_metric')
        ->where('stock_id', $stockId)
        ->orderBy('created_at', 'desc')
        ->first();

    if (!$metrics) {
        return ['error' => 'No financial metrics found'];
    }

    $arr = (array) $metrics;

    // map actual DB columns → friendly keys
    $map = [
        'beta'              => 'beta',
        'market_cap'        => 'market_cap',
        'pe_ratio'          => 'pettm',              // price/earnings TTM
        'eps'               => 'epsttm',             // earnings/share TTM
        'dividend_yield'    => 'currentdividendyieldttm',
        'price_to_book'     => 'pbannual',
        'price_to_sales'    => 'psannual',
        'debt_to_equity'    => 'totaldebt_totalequityannual',
        'return_on_equity'  => 'roerfy',
        'return_on_assets'  => 'roattm',
        'profit_margin'     => 'netprofitmarginttm',
        'operating_margin'  => 'operatingmarginttm',
        'gross_margin'      => 'grossmarginttm',
        'revenue_growth'    => 'revenueGrowthTTMYoy',
        'earnings_growth'   => 'epsGrowthTTMYoy',
        'free_cash_flow'    => 'cashflowpersharettm',
        'current_ratio'     => 'currentratioquarterly',
        'quick_ratio'       => 'quickratioquarterly',
        'ten_day_avg_volume'=> 'ten_day_ave_trading_vol',
        'fifty_two_week_high'=> 'fifty_two_week_high',
        'fifty_two_week_low' => 'fifty_two_week_low',
    ];

    $out = [];
    foreach ($map as $friendly => $col) {
        if (isset($arr[$col]) && is_numeric($arr[$col])) {
            $out[$friendly] = (float) $arr[$col];
        }
    }

    $out['symbol'] = $symbol;
    $out['last_updated'] = $arr['updated_at'] ?? $arr['created_at'] ?? null;

    return $out;
}),

        ]
    ];

    return response()->json($payload, 200);
});

// New: Stock Sentiment News Analysis - dedicated endpoint for sentiment/news data
Route::get('/v1/stock-sentiment-news/{symbol}', function (Request $request, string $symbol) {
    $symbol = strtoupper($request->route('symbol'));
    
    // Calculate 7 days back cutoff
    $sevenDaysAgo = Carbon::now()->subDays(7);
    
    // Helper function to safely execute database queries
    $safeCall = function ($callback, $fallback = null) {
        try {
            $result = $callback();
            return $result ?: $fallback;
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => 'Database query failed: ' . $e->getMessage(),
                'details' => substr($e->getTraceAsString(), 0, 200)
            ];
        }
    };

    // Helper function to create 5-sentence summary from article content
    $createSummary = function ($text, $maxSentences = 5) {
        if (empty($text)) return '';
        
        // Clean and normalize the text
        $text = strip_tags($text);
        $text = html_entity_decode($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        // Split into sentences using multiple delimiters
        $sentences = preg_split('/[.!?]+/', $text);
        $sentences = array_filter(array_map('trim', $sentences));
        
        // Take first maxSentences sentences
        $summary = array_slice($sentences, 0, $maxSentences);
        
        return implode('. ', $summary) . (count($summary) > 0 ? '.' : '');
    };

    $payload = [
        'sentiment_news' => [
            'company_news' => $safeCall(function () use ($symbol, $sevenDaysAgo, $createSummary) {
                // Get stock_id for the symbol
                $stockId = DB::table('stock_symbols')->where('symbol', $symbol)->value('id');
                if (!$stockId) {
                    return ['error' => 'Stock symbol not found'];
                }
                
                // Query stock_company_news table with 7-day filter
                $companyNews = DB::table('stock_company_news')
                    ->where('stock_id', $stockId)
                    ->where('date_time', '>=', $sevenDaysAgo)
                    ->orderBy('date_time', 'desc')
                    ->limit(50) // Limit to 50 most recent news items
                    ->get();
                
                $formatted = [];
                foreach ($companyNews as $item) {
                    $formatted[] = [
                        'headline' => $item->headline ?? 'No headline available',
                        'date' => $item->date_time ?? null,
                        'summary' => $createSummary($item->summary ?? ''),
                        'url' => $item->url ?? null,
                        'source' => $item->source ?? null,
                        'category' => $item->category ?? null,
                        'news_id' => $item->news_id ?? null,
                        'image_url' => $item->image_url ?? null,
                        'related' => $item->related ?? null
                    ];
                }
                
                return [
                    'data' => $formatted
                ];
            }),
            
            'news_sentiment' => $safeCall(function () use ($symbol, $sevenDaysAgo) {
                // Get stock_id for the symbol
                $stockId = DB::table('stock_symbols')->where('symbol', $symbol)->value('id');
                if (!$stockId) {
                    return ['error' => 'Stock symbol not found'];
                }
                
                // Query stock_news_sentiments table with 7-day filter
                $newsSentiments = DB::table('stock_news_sentiments')
                    ->where('stock_id', $stockId)
                    ->get();
                
                $formatted = [];
                foreach ($newsSentiments as $item) {
                    $formatted[] = [
                        'sentiment_bearish' => $item->sentiment_bearish ?? null,
                        'sentiment_bullish' => $item->sentiment_bullish ?? null,
                        'sentiment' => $item->sentiment ?? null,
                        'companynews_score' => $item->companynews_score ?? null,
                        'prev_companynews_score' => $item->prev_companynews_score ?? null,
                        'date' => $item->created_at ?? null,
                        'last_updated' => $item->updated_at ?? null
                    ];
                }
                
                return [
                    'data' => $formatted
                ];
            }),
            
            'social_sentiment' => $safeCall(function () use ($symbol, $sevenDaysAgo) {
                // Get stock_id for the symbol
                $stockId = DB::table('stock_symbols')->where('symbol', $symbol)->value('id');
                if (!$stockId) {
                    return ['error' => 'Stock symbol not found'];
                }
                
                // Query stock_social_sentiments table with 7-day filter
                $socialSentiments = DB::table('stock_social_sentiments')
                    ->where('stock_id', $stockId)
                    ->where('created_at', '>=', $sevenDaysAgo)
                    ->orderBy('at_time', 'desc')
                    ->limit(50) // Limit to 50 most recent social sentiment records
                    ->get();
                
                $formatted = [];
                foreach ($socialSentiments as $item) {
                    $formatted[] = [
                        'at_time' => $item->at_time ?? null,
                        'mentions' => $item->mentions ?? null,
                        'positive_score' => $item->positive_score ?? null,
                        'negative_score' => $item->negative_score ?? null,
                        'positive_mention' => $item->positive_mention ?? null,
                        'negative_mention' => $item->negative_mention ?? null,
                        'score' => $item->score ?? null,
                        'date' => $item->created_at ?? null,
                        'last_updated' => $item->updated_at ?? null
                    ];
                }
                
                return [
                    'data' => $formatted
                ];
            }),
        ]
    ];

    return response()->json($payload, 200);
});

Route::get('/v1/stock-analyst-data/{symbol}', function (Request $request, string $symbol) {
    $symbol = strtoupper($request->route('symbol'));
    
    // Helper function to safely execute controller methods
    $safeCall = function ($callback, $fallback = null) {
        try {
            $result = $callback();
            if ($result instanceof \Illuminate\Http\JsonResponse) {
                return $result->getData(true);
            }
            return $result ?: $fallback;
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => 'Controller call failed: ' . $e->getMessage(),
                'details' => substr($e->getTraceAsString(), 0, 200)
            ];
        }
    };

    // Helper function to filter for past 7 days, else latest
    $filterRecentOrLatest = function ($data, $dateField = 'date') {
        if (!is_array($data)) return $data;

        $sevenDaysAgo = Carbon::today()->subDays(7);
        $today = Carbon::today();

        // normalize array of items
        $items = array_map(function ($item) {
            return (array) $item;
        }, $data);

        // filter for past 7 days
        $recent = array_filter($items, function ($item) use ($dateField, $sevenDaysAgo, $today) {
            if (!isset($item[$dateField])) return false;
            try {
                $d = Carbon::parse($item[$dateField]);
                return $d->between($sevenDaysAgo, $today);
            } catch (\Throwable $e) {
                return false;
            }
        });

        if (!empty($recent)) {
            return array_values($recent);
        }

        // fallback: return the latest record (by date if present)
        usort($items, function ($a, $b) use ($dateField) {
            $da = isset($a[$dateField]) ? strtotime($a[$dateField]) : 0;
            $db = isset($b[$dateField]) ? strtotime($b[$dateField]) : 0;
            return $db <=> $da; // sort desc
        });

        return isset($items[0]) ? [$items[0]] : [];
    };

    // Helper function to format analyst data for consistency
    $formatAnalystData = function ($data, $dataType = 'general') {
        if (!is_array($data)) return $data;
        
        $formatted = [];
        foreach ($data as $item) {
            if (is_object($item)) $item = (array) $item;
            if (!is_array($item)) continue;
            
            $formattedItem = [];
            
            switch ($dataType) {
                case 'upgrade_downgrade':
                // accept grade_time from DB and normalize to 'date'
                $rawDate = $item['grade_time'] ?? $item['date'] ?? $item['upgrade_downgrade_date'] ?? null;

                $formattedItem = [
                    // table has stock_id, not symbol -> fall back to route symbol
                    'symbol'     => $item['symbol'] ?? $item['symbol'] ?? null,
                    'date'       => $rawDate ? \Carbon\Carbon::parse($rawDate)->toDateString() : null,
                    'firm'       => $item['company'] ?? $item['firm'] ?? null,
                    'from_grade' => $item['fromGrade'] ?? $item['from_grade'] ?? null,
                    'to_grade'   => $item['toGrade'] ?? $item['to_grade'] ?? null,
                    'action'     => $item['action'] ?? null,
                ];
                break;
                    
                case 'recommendation':
                    $formattedItem = [
                        'symbol' => $item['symbol'] ?? null,
                        'date' => $item['period'] ?? $item['date'] ?? null,
                        'buy' => $item['buy'] ?? null,
                        'hold' => $item['hold'] ?? null,
                        'sell' => $item['sell'] ?? null,
                        'strong_buy' => $item['strongBuy'] ?? $item['strong_buy'] ?? null,
                        'strong_sell' => $item['strongSell'] ?? $item['strong_sell'] ?? null,
                    ];
                    break;
                    
                case 'analyst_reports':
                    $formattedItem = [
                        'symbol' => $item['symbol'] ?? null,
                        'date' => $item['date'] ?? $item['published_date'] ?? null,
                        'analyst' => $item['analyst'] ?? $item['analyst_name'] ?? null,
                        'firm' => $item['firm'] ?? $item['company'] ?? null,
                        'rating' => $item['rating'] ?? null,
                        'price_target' => $item['price_target'] ?? $item['target_price'] ?? null,
                        'report_title' => $item['title'] ?? $item['report_title'] ?? null,
                    ];
                    break;
                    
                default:
                    $formattedItem = $item;
            }
            
            $formattedItem = array_filter($formattedItem, function($value) {
                return $value !== null && $value !== '';
            });
            
            if (!empty($formattedItem)) {
                $formatted[] = $formattedItem;
            }
        }
        
        return $formatted;
    };

    $payload = [
        'analyst_data' => [
            'upgrade_downgrade' => $safeCall(function () use ($symbol, $formatAnalystData, $filterRecentOrLatest) {
                $raw = app(\App\Http\Controllers\endpoints\v1\StockUpgradeDowngradeController::class)
                    ->getUpgradeDowngradeBySymbol7Days($symbol);

                if ($raw instanceof \Illuminate\Http\JsonResponse) $raw = $raw->getData(true);

                $formatted = $formatAnalystData($raw, 'upgrade_downgrade');

                // Fallback to direct DB if nothing came back or dates are missing
                if (empty($formatted)) {
                    $stockId = DB::table('stock_symbols')->where('symbol', $symbol)->value('id');
                    if ($stockId) {
                        $rows = DB::table('stock_upgrade_downgrade')
                            ->where('stock_id', $stockId)
                            ->orderBy('grade_time', 'desc')
                            ->limit(20)
                            ->get()
                            ->toArray();

                        $formatted = $formatAnalystData($rows, 'upgrade_downgrade');
                    }
                }

                return $filterRecentOrLatest($formatted, 'date'); // now ‘date’ exists (from grade_time)
            }),
            'stock_recommendation' => $safeCall(function () use ($symbol, $formatAnalystData, $filterRecentOrLatest) {
                // call your 7-day controller
                $raw = app(\App\Http\Controllers\endpoints\v1\StockRecommendationController::class)
                    ->getStockRecommendationBySymbol7days($symbol);

                if ($raw instanceof \Illuminate\Http\JsonResponse) {
                    $raw = $raw->getData(true);
                }

                // IMPORTANT: unwrap the "data" key when present
                $items = (isset($raw['data']) && is_array($raw['data']))
                    ? $raw['data']
                    : (is_array($raw) ? $raw : []);

                // now format the array of rows
                $formatted = $formatAnalystData($items, 'recommendation');

                // if you still want the 7-day filter/fallback at the route level, keep this:
                return $filterRecentOrLatest($formatted, 'date');
            }),
            'analyst_reports' => $safeCall(function () use ($symbol, $formatAnalystData, $filterRecentOrLatest) {
                $rawData = app(\App\Http\Controllers\v1\StockInvestmentReportController::class)
                    ->getAnalystReportsSingle7days($symbol);
                if ($rawData instanceof \Illuminate\Http\JsonResponse) {
                    $rawData = $rawData->getData(true);
                }
                $formatted = $formatAnalystData($rawData, 'analyst_reports');
                return $filterRecentOrLatest($formatted, 'date');
            }),
        ]
    ];

    return response()->json($payload, 200);
});

// New: Stock Analysis Orchestrator - single consolidated endpoint
Route::get('/v1/stock-analysis-orchestrator/{symbol}', function (Request $request, string $symbol) {
    $symbol = strtoupper($request->route('symbol'));
    
    // Helper function to safely execute controller methods
    $safeCall = function ($callback, $fallback = null) {
        try {
            $result = $callback();
            // If it's a JsonResponse, get the data
            if ($result instanceof \Illuminate\Http\JsonResponse) {
                return $result->getData(true);
            }
            return $result ?: $fallback;
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => 'Controller call failed: ' . $e->getMessage(),
                'details' => substr($e->getTraceAsString(), 0, 200)
            ];
        }
    };

    // Create mock request objects for controllers that need them
    $mockRequest = new Request(['symbols' => $symbol]);
    $mockRequest->merge(['symbol' => $symbol]);

    $payload = [
        'fundamentals' => [
            'stock_metrics' => $safeCall(function () use ($mockRequest) {
                return \App\Http\Controllers\v1\StockArticleReference::getStockMetricsSingle($mockRequest);
            }),
            'basic_financial_metric' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\endpoints\v1\StockBasicFinancialMetricController::class)
                    ->getAllBasicFinancialMetrics($symbol);
            }),
            'fundamental_percentage' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\v1\StockTradingScoreController::class)
                    ->getFundamentalPercentageSingle($symbol);
            }),
        ],
        'technicals' => [
            'candlesticks_daily' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\endpoints\v1\StockCandleDailyController::class)
                    ->getAllStockCandleDaily($symbol);
            }),
            'stock_indicators' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\Indicators\LatestController::class)
                    ->getStockIndicators($symbol);
            }),
        ],
        'quant_risk' => [
            'current_price_market_cap' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\user\UserPortfoliosController::class)
                    ->getCurrentPriceAndMarketCap($symbol);
            }),
            'price_target' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\endpoints\v1\StockPriceTargetController::class)
                    ->getPriceTargetBySymbol($symbol);
            }),
            'price_prediction' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\v1\StockPricePredictionController::class)
                    ->fetchSingle($symbol);
            }),
        ],
        'sentiment_news' => [
            'company_news' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\endpoints\v1\StockCompanyNewsController::class)
                    ->getCompanyNewsBySymbol($symbol);
            }),
            'news_sentiment' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\endpoints\v1\StockNewsSentimentController::class)
                    ->getNewsSentimentBySymbol($symbol);
            }),
            'social_sentiment' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\endpoints\v1\StockSocialSentimentController::class)
                    ->getSocialSentimentBySymbol($symbol);
            }),
        ],
        'analyst_data' => [
            'upgrade_downgrade' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\endpoints\v1\StockUpgradeDowngradeController::class)
                    ->getUpgradeDowngradeBySymbol($symbol);
            }),
            'stock_recommendation' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\endpoints\v1\StockRecommendationController::class)
                    ->getStockRecommendationBySymbol($symbol);
            }),
            'analyst_reports' => $safeCall(function () use ($symbol) {
                return app(\App\Http\Controllers\v1\StockInvestmentReportController::class)
                    ->getAnalystReportsSingle($symbol);
            }),
        ],
        'meta' => [
            'symbol' => $symbol,
            'generated_at' => now()->toIso8601String(),
            'total_endpoints_called' => 13,
            'execution_method' => 'direct_controller_calls'
        ],
    ];

    return response()->json($payload, 200);
});