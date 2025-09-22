<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

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
    Route::get('/watch-current-price-market-cap', [UserPortfoliosController::class, 'updateDailyPLAndMarketCap']);
    Route::get('/log-daily-portfolio-total-value', [UserPortfoliosController::class, 'logDailyPortfolioTotalValue']);
    Route::get('/get-all-daily-pl-unrealized-pl/{user_id}', [UserPortfoliosController::class, 'calculateUserPL']);
    Route::get('/get-stocks-by-portfolio/{user_id}/{portfolio_id}', [UserPortfoliosController::class, 'getStocksByPortfolio']);
    Route::get('/get-user-portfolios/{user_id}', [UserPortfoliosController::class, 'getUserPortfolios']);
    Route::get('/user/portfolios/details/{user_id}', [UserPortfoliosController::class, 'getUserPortfoliosDetails']);
    Route::get('/user-portfolio-logs', [UserPortfoliosController::class, 'getUserPortfolioLogs']);
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

Route::middleware('\App\Http\Middleware\UserApiToken')->group(function () {
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
});

/**
 * Protected routes (misc)
 */
Route::middleware('\App\Http\Middleware\UserApiToken')->group(function () {
    Route::get('/v1/get-top-100-stocks-by-market-cap-v2', [StockArticleReference::class, 'getAllTop100StocksByMarketCap']);
});

/**
 * Questionnaire (protected)
 */
Route::middleware('\App\Http\Middleware\UserApiToken')->group(function () {
    Route::get('/v1/questionnaire/questions-only', [UserQuestionnaireController::class, 'getQuestions']);
    Route::get('/v1/questionnaire/choices', [UserQuestionnaireController::class, 'getChoices']);
    Route::get('/v1/questionnaire/profile-tags', [UserQuestionnaireController::class, 'getProfileTags']);
    Route::get('/v1/questionnaire/user-suggested-tag', [UserQuestionnaireController::class, 'getUserSuggestedTag']);
    Route::get('/v1/questionnaire/user-profile-tags', [UserQuestionnaireController::class, 'getUserInvestmentPTags']);
});

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

/**
 * Diagnostics / Utilities
 */
Route::get('/v1/stocks/negative-market-cap', function () {
    $stocks = DB::table('stock_symbol_info')
        ->join('stock_symbols', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')
        ->where('stock_symbol_info.market_cap', '<', 0)
        ->select('stock_symbol_info.*', 'stock_symbols.symbol as stock_name')
        ->get();

    echo '<pre>';
    print_r($stocks);
    echo '</pre>';
});

/**
 * ──────────────────────────────────────────────────────────────────────────────
 * Artisan-trigger endpoints (moved to bottom)
 * ──────────────────────────────────────────────────────────────────────────────
 */

Route::get('/v1/trigger-process-stock-fundamentals', function () {
    Artisan::call('stocks:fundamentals');
    return response('Stocks process fundamentals triggered', 200);
});

// Separate explicit artisan endpoint for only the fundamentals command
Route::get('/v1/trigger-fundamentals-only', function () {
    Artisan::call('stocks:fundamentals');
    return response('Stocks fundamentals (separate endpoint) triggered', 200);
});

Route::get('/v1/trigger-process-stock-fundamentals-p2', function () {
    Artisan::call('stocks:fundamentals_p2');
    return response('Stocks process fundamentals triggered', 200);
});
Route::get('/v1/trigger-indicators-batch1', function () {
    Artisan::call('stocks:indicators_batch1');
    return response('Stocks indicators batch 1 triggered', 200);
});
Route::get('/v1/trigger-indicators-batch2', function () {
    Artisan::call('stocks:indicators_batch2');
    return response('Stocks indicators batch 2 triggered', 200);
});
Route::get('/v1/trigger-trading-score', function () {
    Artisan::call('stocks:trading_score');
    return response('Stocks trading score triggered', 200);
});
Route::get('/v1/trigger-historical-indicators', function () {
    Artisan::call('stocks:historical-indicators');
    return response('Stocks historical indicators triggered', 200);
});
Route::get('/v1/trigger-candles', function () {
    Artisan::call('stocks:candles');
    return response('Stocks candles triggered', 200);
});
Route::get('/v1/trigger-earnings-calendar', function () {
    // call to the command
    Artisan::call('stocks:earnings_calendar');
    return response('Stocks earnings calendar triggered', 200);
});

Route::get('/v1/trigger-company-news', function () {
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