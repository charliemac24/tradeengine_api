<?php

/**Incoming Articles**/
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v1\StockIncomingArticlesController;



Route::post('/v1/send_article', [StockIncomingArticlesController::class, 'sendArticle']);


use App\Http\Controllers\user\UserPortfoliosController;
use App\Http\Controllers\user\UserWatchListsController;
use App\Http\Controllers\user\UserController;


Route::middleware('\App\Http\Middleware\UserApiToken')->group(function () {
    // User Portfolios Routes
    Route::post('/v1/portfolio/create', [UserPortfoliosController::class, 'createPortfolio']);
    Route::post('/v1/portfolio/delete-stock', [UserPortfoliosController::class, 'deleteStockFromPortfolio']);
    Route::post('/v1/portfolio/delete', [UserPortfoliosController::class, 'deletePortfolio']);
    Route::post('/v1/portfolio/add_stock', [UserPortfoliosController::class, 'addStockToPortfolio']);

    // User Watchlists Routes
    Route::post('/v1/watchlists/create', [UserWatchListsController::class, 'createWatchlist']);
    Route::post('/v1/watchlists/add-stock', [UserWatchListsController::class, 'addStockToSpecificWatchlist']);
    Route::post('/v1/watchlists/delete-stock', [UserWatchListsController::class, 'deleteStockFromWatchlist']);
    Route::post('/v1/watchlists/delete', [UserWatchListsController::class, 'deleteWatchlist']);



});




use App\Http\Controllers\user\UserQuestionnaireController;

Route::middleware('\App\Http\Middleware\UserApiToken')->group(function () {
    Route::post('/v1/questionnaire/add-choice', [UserQuestionnaireController::class, 'addUserChoice']);
    Route::post('/v1/questionnaire/add-profile-tag', [UserQuestionnaireController::class, 'addInvestmentPTag']);
    Route::post('/v1/questionnaire/update-profile-tag', [UserQuestionnaireController::class, 'updateInvestmentPTag']);
});


use App\Http\Controllers\system\StockEventLoggerController;

Route::post('/v1/stock-events/log-latest-article', [StockEventLoggerController::class, 'generateLogIfLatestArticle']);

// User Registration Route
Route::post('/v1/user/signup', [UserController::class, 'signup']);
Route::post('/v1/user/signin', [UserController::class, 'signin']);
Route::post('/v1/user/logout', [UserController::class, 'logout']);
Route::post('/v1/signup/google', [UserController::class, 'signupWithGoogle']);

// Stock Investment Report Route
use App\Http\Controllers\v1\StockInvestmentReportController;
use App\Http\Controllers\v1\StockAnalysisQAController;
Route::middleware('\App\Http\Middleware\UserApiToken')->group(function () {
    Route::post('/v1/analyst-report', [StockInvestmentReportController::class, 'saveAnalystReport']);
    Route::post('/v1/user/stock-query', [StockAnalysisQAController::class, 'saveUserStockQuery']);
});

// User External Subscription Route
Route::post('/v1/user/external-subscription', [UserController::class, 'saveExternalSubscription']);

use App\Http\Controllers\endpoints\v1\StockUserChatbotQA;
Route::post('/v1/chatbot-qa', [StockUserChatbotQA::class, 'saveChatbotQA']);
Route::post('/v1/portfolio-analyzer-qa', [StockUserChatbotQA::class, 'saveChatPortfolioAnalyzerQA']);

use App\Http\Controllers\user\UserActivityLogger;

Route::post('/v1/user/log-activity', [UserActivityLogger::class, 'log']);

use App\Http\Controllers\user\UserSocialPost;

Route::post('/v1/social-posts/store', [UserSocialPost::class, 'storeWithAttachment']);
Route::post('/v1/social-posts/delete', [UserSocialPost::class, 'delete']);
Route::post('/v1/social-posts/update', [UserSocialPost::class, 'update']);
Route::post('/v1/social-posts/comment', [UserSocialPost::class, 'comment']);
Route::post('/v1/social-posts/share', [UserSocialPost::class, 'share']);
Route::post('/v1/social-posts/follow', [UserSocialPost::class, 'follow']);
Route::post('/v1/social-posts/view', [UserSocialPost::class, 'viewPost']);
Route::post('/v1/social-posts/unfollow', [UserSocialPost::class, 'unfollow']);
Route::post('/v1/social-posts/unshare', [UserSocialPost::class, 'unshare']);
Route::post('/v1/social-posts/delete-comment', [UserSocialPost::class, 'deleteComment']);
Route::post('/v1/social-posts/love', [UserSocialPost::class, 'lovePost']);
Route::post('/v1/social-posts/unlove', [UserSocialPost::class, 'unlovePost']);

Route::post('/v1/user/use-ai-token', [UserController::class, 'useAiToken']);

Route::post(
    '/v1/external-subscription/update',
    [UserController::class, 'updateExternalSubscription']
);
use App\Http\Controllers\v1\StockCandleController;
Route::post('/v1/save-price-prediction', [StockCandleController::class, 'savePricePrediction']);

use App\Http\Controllers\v1\StockPricePredictionController;
Route::post('/v1/stock-price-prediction', [StockPricePredictionController::class, 'store']);

use App\Http\Controllers\endpoints\v1\StockEarningsCalendarController;
Route::get('/v1/earnings-calendar/hide-incomplete', [StockEarningsCalendarController::class, 'hideIncompletePastEarnings']);

