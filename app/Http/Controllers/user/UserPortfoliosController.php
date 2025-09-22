<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Class UserPortfoliosController
 *
 * Handles operations related to user portfolios.
 *
 * @package App\Http\Controllers\user
 */
class UserPortfoliosController extends Controller
{
    /**
     * Create a new portfolio for the user.
     *
     * @param Request $request The HTTP request containing portfolio data.
     * @return \Illuminate\Http\JsonResponse A JSON response indicating success or failure.
     */
    public function createPortfolio(Request $request)
    {
        $userPlan = $request->input('userPlan');
        $userId = $request->input('user_id');
        if (!$userId) return response()->json(['error' => 'User not authenticated'], 401);

        /**if ( "Trade Engine Access" !== $userPlan || "Trade Engine Professional" !== $userPlan ) {
            return response()->json([
                    'message' => 'You don\'t have permission to create a portfolio with this plan.'
                ], 403);
        }

        if ($userPlan === 'Trade Engine Access') {
            $portfolioCount = DB::table('user_portfolios')->where('user_id', $userId)->count();
            if ($portfolioCount >= 1) {
                return response()->json([
                    'message' => 'Trade Engine Access users can only create 1 portfolio.'
                ], 403);
            }
        }**/                

        $portfolioId = DB::table('user_portfolios')->insert([
            'user_id' => $userId,
            'portfolio' => $request->input('portfolio'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update investment_profile_choices for this user with the new portfolio_id
        DB::table('investment_profile_choices')
        ->where('user_id', $userId)
        ->where(function($query) {
            $query->whereNull('portfolio_id')
                ->orWhere('portfolio_id', 0);
        })
        ->update(['portfolio_id' => $portfolioId]);

        return response()->json(['message' => 'Portfolio successfully created']);
    }

    /**
     * Add a stock to the user's portfolio.
     *
     * @param Request $request The HTTP request containing stock and portfolio data.
     * @return \Illuminate\Http\JsonResponse A JSON response indicating success or failure.
     */
    public function addStockToPortfolio(Request $request)
    {
        $userPlan = $request->input('userPlan');

        /**if ( "Trade Engine Access" !== $userPlan && "Trade Engine Professional" !== $userPlan ) {
            return response()->json([
                    'message' => 'You don\'t have permission to create a portfolio with this plan.'
                ], 403);
        }**/

        $userId = $request->input('user_id'); // Auth::id(); // Get the authenticated user's ID
        if (!$userId) return response()->json(['error' => 'User not authenticated'], 401);

       /**if ($userPlan === 'Trade Engine Access') {
            $stockCount = DB::table('user_stock_portfolio')
                ->where('user_id', $userId)
                ->where('portfolio_id', $portfolioId)
                ->count();

            if ($stockCount >= 5) {
                return response()->json([
                    'message' => 'Trade Engine Access users can only have up to 5 stocks in their portfolio.'
                ], 403);
            }
        } **/

        $request->validate([
            'stock_id' => 'required|exists:stock_symbols,id',
            'shares' => 'required|integer|min:1', // Validate shares as a required integer greater than or equal to 1
            'price' => 'required|numeric|min:0', // Validate price as a required numeric value greater than or equal to 0
            'transaction' => 'required|in:buy,sell', // Validate transaction as either "buy" or "sell"
        ]);

        // SQL query to join tables and retrieve required fields
        $stockData = DB::table('stock_quote')
            ->join('stock_basic_financials_metric', 'stock_quote.stock_id', '=', 'stock_basic_financials_metric.stock_id')
            ->where('stock_quote.stock_id', $request->input('stock_id'))
            ->select(
                'stock_quote.current_price as last_price',
                'stock_quote.difference',
                'stock_quote.total_percentage',
                'stock_basic_financials_metric.market_cap'
            )
            ->orderBy('stock_quote.updated_at', 'desc')
            ->first();

        if (!$stockData) {
            return response()->json(['error' => 'Stock data not found'], 404);
        }

        // Insert or update the user_stock_portfolio table and get the ID
        $userStockPortfolioId = DB::table('user_stock_portfolio')->updateOrInsert(
            [
                'stock_id' => $request->input('stock_id'),
                'user_id' => $userId,
                'portfolio_id' => $request->input('portfolio_id'), // Placeholder for portfolio_id, assuming a single portfolio for simplicity
            ],
            [
                'position' => 0, // Placeholder, will be updated later
                'last_price' => $stockData->last_price,
                'change_percentage' => $stockData->total_percentage,
                'market_cap' => $stockData->market_cap,
                'avg_price' => 0, // Placeholder, will be updated later
                'cost_basis' => 0, // Placeholder, will be updated later
                'unrealized_pl' => 0, // Placeholder, will be updated later
                'daily_pl' => 0, // Placeholder, will be updated later
                'updated_at' => now(),
            ]
        );

        // Retrieve the ID of the inserted or updated record
        $userStockPortfolioId = DB::table('user_stock_portfolio')
            ->where('stock_id', $request->input('stock_id'))
            ->where('user_id', $userId)
            ->where('portfolio_id', $request->input('portfolio_id'))
            ->value('id');

        // Handle the transaction (buy or sell)
        $this->handleTransaction(
            $userId,
            $userStockPortfolioId,
            $request->input('stock_id'),
            $request->input('shares'),
            $request->input('price'),
            $request->input('transaction')
        );

        // Recalculate total shares and total cost
        $totalShares = $this->getTotalSharesByPortfolioId($userStockPortfolioId);
        $totalCost = $this->getTotalPriceByPortfolioId($userStockPortfolioId);

        // Calculate the average price and cost basis
        $averagePrice = $totalShares > 0 ? $totalCost / $totalShares : 0;
        $costBasis = $totalShares * $averagePrice;

        // Update the user_stock_portfolio table with recalculated values
        DB::table('user_stock_portfolio')->where('id', $userStockPortfolioId)->update([
            'position' => $totalShares,
            'avg_price' => $averagePrice,
            'cost_basis' => $costBasis,
            'market_cap' => $stockData->last_price * $totalShares,
            'unrealized_pl' => ($stockData->last_price * $totalShares) - $costBasis,
            'daily_pl' => $stockData->difference * $totalShares,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Transaction processed successfully',
            'total_shares' => $totalShares,
            'average_price' => $averagePrice,
            'cost_basis' => $costBasis,
        ]);
    }

    /**
     * Delete a stock from the user's portfolio.
     *
     * @param Request $request The HTTP request containing the stock ID to delete.
     * @return \Illuminate\Http\JsonResponse A JSON response indicating success or failure.
     */
    public function deleteStockFromPortfolio(Request $request)
    {
        $request->validate(['stock_id' => 'required|exists:stock_symbols,id']);
        $userId = $request->input('user_id'); // Auth::id(); // Get the authenticated user's ID
        if (!$userId) return response()->json(['error' => 'User not authenticated'], 401);

        // Retrieve the user_stock_portfolio record
        $userStockPortfolio = DB::table('user_stock_portfolio')
            ->where('user_id', $userId)
            ->where('stock_id', $request->input('stock_id'))
            ->where('portfolio_id', $request->input('portfolio_id'))
            ->first();

        if (!$userStockPortfolio) {
            return response()->json(['error' => 'Stock not found in portfolio'], 404);
        }

        // Delete related records from user_shares table
        DB::table('user_shares')
            ->where('user_stock_portfolio_id', $userStockPortfolio->id)
            ->delete();

        // Delete the stock from user_stock_portfolio table
        $deleted = DB::table('user_stock_portfolio')
            ->where('id', $userStockPortfolio->id)
            ->delete();

        return $deleted
            ? response()->json(['message' => 'Stock and related share logs removed from portfolio successfully'])
            : response()->json(['error' => 'Failed to delete stock from portfolio'], 500);
    }

    /**
     * Delete a portfolio and all related records for a user.
     *
     * @param Request $request The HTTP request containing the portfolio ID to delete.
     * @return \Illuminate\Http\JsonResponse A JSON response indicating success or failure.
     */
    public function deletePortfolio(Request $request)
    {
        $request->validate(['portfolio_id' => 'required|exists:user_portfolios,id']);
        $userId = $request->input('user_id'); // Auth::id(); // Get the authenticated user's ID
        if (!$userId) return response()->json(['error' => 'User not authenticated'], 401);

        // Check if the portfolio belongs to the user
        $portfolio = DB::table('user_portfolios')
            ->where('id', $request->input('portfolio_id'))
            ->where('user_id', $userId)
            ->first();

        if (!$portfolio) {
            return response()->json(['error' => 'Portfolio not found or does not belong to the user'], 404);
        }

        // Get all user_stock_portfolio IDs related to this portfolio
        $userStockPortfolioIds = DB::table('user_stock_portfolio')
            ->where('portfolio_id', $portfolio->id)
            ->pluck('id');

        // Delete related user_shares
        DB::table('user_shares')
            ->whereIn('user_stock_portfolio_id', $userStockPortfolioIds)
            ->delete();

        // Delete related user_stock_portfolio records
        DB::table('user_stock_portfolio')
            ->where('portfolio_id', $portfolio->id)
            ->delete();

        // Delete related user_portfolio_total_value records
        DB::table('user_portfolio_total_value')
            ->where('portfolio_id', $portfolio->id)
            ->delete();

        // Delete the portfolio itself
        DB::table('user_portfolios')
            ->where('id', $portfolio->id)
            ->delete();

        return response()->json(['message' => 'Portfolio and all related records deleted successfully']);
    }

    /**
     * Get the total shares for a specific user_stock_portfolio_id.
     *
     * @param int $userStockPortfolioId The ID of the user_stock_portfolio record.
     * @return int The total number of shares.
     */
    public function getTotalSharesByPortfolioId(int $userStockPortfolioId): int
    {
        return DB::table('user_shares')
            ->where('user_stock_portfolio_id', $userStockPortfolioId)
            ->sum('shares');
    }

    /**
     * Get the total price for a specific user_stock_portfolio_id.
     *
     * @param int $userStockPortfolioId The ID of the user_stock_portfolio record.
     * @return float The total price.
     */
    public function getTotalPriceByPortfolioId(int $userStockPortfolioId): float
    {
        return DB::table('user_shares')
            ->where('user_stock_portfolio_id', $userStockPortfolioId)
            ->sum(DB::raw('shares * price'));
    }

    /**
     * Handle the transaction for buying or selling stocks.
     *
     * @param int $userId The ID of the user.
     * @param int $userStockPortfolioId The ID of the portfolio.
     * @param int $stockId The ID of the stock.
     * @param int $shares The number of shares.
     * @param float $price The price of the shares.
     * @param string $transaction The transaction type ('buy' or 'sell').
     * @return void
     */
    private function handleTransaction($userId, $userStockPortfolioId, $stockId, $shares, $price, $transaction)
    {
        if ($transaction === 'buy') {
            $this->handleBuyTransaction($userId, $userStockPortfolioId, $stockId, $shares, $price);
        } elseif ($transaction === 'sell') {
            $this->handleSellTransaction($userId, $userStockPortfolioId, $stockId, $shares);
        }
    }

    /**
     * Handle the buy transaction for stocks.
     *
     * @param int $userId The ID of the user.
     * @param int $userStockPortfolioId The ID of the portfolio.
     * @param int $stockId The ID of the stock.
     * @param int $shares The number of shares.
     * @param float $price The price of the shares.
     * @return void
     */
    private function handleBuyTransaction($userId, $userStockPortfolioId, $stockId, $shares, $price)
    {
        // Add a new row to the user_shares table
        DB::table('user_shares')->insert([
            'user_id' => $userId,
            'user_stock_portfolio_id' => $userStockPortfolioId,
            'shares' => $shares,
            'price' => $price,
            'cost' => $shares * $price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Log the buy action
        $symbol = DB::table('stock_symbols')->where('id', $stockId)->value('symbol');
        $this->logPortfolioAction($userId, "User bought $shares shares of $symbol at $$price each.", $userStockPortfolioId);
    }

    /**
     * Handle the sell transaction for stocks.
     *
     * @param int $userId The ID of the user.
     * @param int $userStockPortfolioId The ID of the portfolio.
     * @param int $stockId The ID of the stock.
     * @param int $shares The number of shares.
     * @return void
     * @throws \Exception If the user tries to sell more shares than they own.
     */
    private function handleSellTransaction($userId, $userStockPortfolioId, $stockId, $shares)
    {
        // Calculate the total shares the user owns
        $totalOwnedShares = DB::table('user_shares')
            ->where('user_stock_portfolio_id', $userStockPortfolioId)
            ->sum('shares');

        // Check if the user is trying to sell more shares than they own
        if ($shares > $totalOwnedShares) {
            throw new \Exception('Not enough shares to sell');
        }

        // Deduct shares from the user_shares table
        $remainingShares = $shares;
        $shareLogs = DB::table('user_shares')
            ->where('user_stock_portfolio_id', $userStockPortfolioId)
            ->orderBy('created_at', 'asc')
            ->get();

        $totalSellAmount = 0;
        $totalSharesSold = 0;

        foreach ($shareLogs as $log) {
            if ($remainingShares <= 0) break;

            if ($log->shares <= $remainingShares) {
                // Deduct all shares from this row
                $totalSellAmount += $log->shares * $log->price;
                $totalSharesSold += $log->shares;
                $remainingShares -= $log->shares;
                DB::table('user_shares')->where('id', $log->id)->delete();
            } else {
                // Deduct partial shares from this row
                $totalSellAmount += $remainingShares * $log->price;
                $totalSharesSold += $remainingShares;
                DB::table('user_shares')->where('id', $log->id)->update([
                    'shares' => $log->shares - $remainingShares,
                    'cost' => ($log->shares - $remainingShares) * $log->price,
                    'updated_at' => now(),
                ]);
                $remainingShares = 0;
            }
        }

        // Log the sell action with price info
        $symbol = DB::table('stock_symbols')->where('id', $stockId)->value('symbol');
        $sellPriceInfo = $totalSharesSold > 0 ? number_format($totalSellAmount / $totalSharesSold, 2) : 0;
        $this->logPortfolioAction(
            $userId,
            "User sold $shares shares of $symbol at average price $$sellPriceInfo.",
            $userStockPortfolioId
        );
    }

    /**
     * Update the daily_pl and market_cap fields for each stock in the user_stock_portfolio table.
     *
     * @return void
     */
    public function updateDailyPLAndMarketCap()
    {
        // Retrieve all user_stock_portfolio records
        $userStockPortfolios = DB::table('user_stock_portfolio')->get();

        foreach ($userStockPortfolios as $portfolio) {
            // Get the current closing price and market cap from the respective tables
            $stockData = DB::table('stock_quote')
                ->join('stock_symbol_info', 'stock_quote.stock_id', '=', 'stock_symbol_info.stock_id')
                ->where('stock_quote.stock_id', $portfolio->stock_id)
                ->select('stock_quote.current_price', 'stock_symbol_info.market_cap')
                ->first();

            if ($stockData) {
                // Calculate the daily_pl (difference * total shares)
                $dailyPL = ($stockData->current_price - $portfolio->last_price) * $portfolio->position;

                // Update the user_stock_portfolio table with the new daily_pl and market_cap
                DB::table('user_stock_portfolio')
                    ->where('id', $portfolio->id)
                    ->update([
                        'daily_pl' => $dailyPL,
                        'market_cap' => $stockData->market_cap,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * Calculate the total daily_pl and unrealized_pl for a user.
     *
     * @param int $userId The ID of the user.
     * @return array The total daily_pl and unrealized_pl.
     */
    public function calculateUserPL(int $userId): array
    {
        // Retrieve all user_stock_portfolio records for the user
        $userPortfolios = DB::table('user_stock_portfolio')
            ->where('user_id', $userId)
            ->select('daily_pl', 'unrealized_pl')
            ->get();

        // Calculate the total daily_pl and unrealized_pl
        $totalDailyPL = $userPortfolios->sum('daily_pl');
        $totalUnrealizedPL = $userPortfolios->sum('unrealized_pl');

        return [
            'total_daily_pl' => $totalDailyPL,
            'total_unrealized_pl' => $totalUnrealizedPL,
        ];
    }

    /**
     * Get all the stocks added to a specific portfolio of a user,
     * including investment profile tag information.
     *
     * @param int $userId The ID of the user.
     * @param int $portfolioId The ID of the portfolio.
     * @return \Illuminate\Http\JsonResponse A JSON response containing the stocks in the portfolio.
     */
    public function getStocksByPortfolio(int $userId, int $portfolioId): \Illuminate\Http\JsonResponse
    {
        // Validate that the portfolio belongs to the user
        $portfolioExists = DB::table('user_portfolios')
            ->where('id', $portfolioId)
            ->where('user_id', $userId)
            ->exists();

        if (!$portfolioExists) {
            return response()->json(['error' => 'Portfolio not found or does not belong to the user'], 404);
        }

        // Retrieve all stocks in the specified portfolio
        $stocks = DB::table('user_stock_portfolio')
            ->join('stock_symbols', 'user_stock_portfolio.stock_id', '=', 'stock_symbols.id')
            ->join('investment_p_tag', 'investment_p_tag.portfolio_id', '=', 'user_stock_portfolio.portfolio_id')
            ->join('investment_profile_tags', 'investment_profile_tags.id', '=', 'investment_p_tag.profile_tag')
            ->where('user_stock_portfolio.portfolio_id', $portfolioId)
            ->where('user_stock_portfolio.user_id', $userId)
            ->select(
                'stock_symbols.symbol',
                'stock_symbols.symbol',
                'user_stock_portfolio.user_id',
                'investment_p_tag.profile_tag AS risk_profile_id',
                'investment_profile_tags.suggested_tag AS risk_profile_name',
                'user_stock_portfolio.portfolio_id',
                'user_stock_portfolio.stock_id',
                'user_stock_portfolio.position',
                'user_stock_portfolio.avg_price',
                'user_stock_portfolio.cost_basis',
                'user_stock_portfolio.unrealized_pl',
                'user_stock_portfolio.daily_pl',
                'user_stock_portfolio.market_cap AS market_value',
                'user_stock_portfolio.updated_at'
            )
            ->get();

        return response()->json(['stocks' => $stocks]);
    }

    /**
     * Get all portfolios for a specific user.
     *
     * @param int $userId The ID of the user.
     * @return \Illuminate\Http\JsonResponse A JSON response containing the user's portfolios.
     */
    public function getUserPortfolios(int $userId): \Illuminate\Http\JsonResponse
    {
        // Validate that the user exists
        $userExists = DB::table('users')->where('id', $userId)->exists();

        if (!$userExists) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Retrieve all portfolios for the user
        $portfolios = DB::table('user_portfolios')
            ->where('user_id', $userId)
            ->select('id', 'portfolio', 'created_at', 'updated_at')
            ->get();

        return response()->json(['portfolios' => $portfolios]);
    }

    /**
     * Compute the total market_cap for each portfolio by user within the day and log the result.
     *
     * @return \Illuminate\Http\JsonResponse A JSON response indicating success or failure.
     */
    public function logDailyPortfolioTotalValue(): \Illuminate\Http\JsonResponse
    {
        // Get the start and end of the current day
        $startOfDay = now()->startOfDay();
        $endOfDay = now()->endOfDay();

        // Retrieve total market_cap for each portfolio by user within the day
        $portfolios = DB::table('user_stock_portfolio')
            ->whereBetween('created_at', [$startOfDay, $endOfDay]) // Filter records created within the day
            ->select('user_id', 'portfolio_id', DB::raw('SUM(CAST(market_cap AS DECIMAL(12,2))) as total_value')) 
            ->groupBy('user_id', 'portfolio_id')
            ->get();
            
        // Iterate through each portfolio and log the total value
        foreach ($portfolios as $portfolio) {
            // Check if a record already exists for the same user_id, portfolio_id, and day
            $existingRecord = DB::table('user_portfolio_total_value')
                ->where('user_id', $portfolio->user_id)
                ->where('portfolio_id', $portfolio->portfolio_id)
                ->whereDate('created_at', now()->toDateString()) // Check if the record is for the same day
                ->first();

            if ($existingRecord && now()->lessThan(now()->endOfDay())) {
                // Update the existing record if the end of the day hasn't passed
                DB::table('user_portfolio_total_value')
                    ->where('id', $existingRecord->id)
                    ->update([
                        'total_value' => $portfolio->total_value,
                        'updated_at' => now(),
                    ]);
            } else {
                // Insert a new record if no existing record or the end of the day has passed
                DB::table('user_portfolio_total_value')->insert([
                    'user_id' => $portfolio->user_id,
                    'portfolio_id' => $portfolio->portfolio_id,
                    'total_value' => $portfolio->total_value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['message' => 'Daily portfolio total values logged successfully']);
    }

    /**
     * Get detailed information about all portfolios and their stocks for a specific user.
     *
     * @param int $userId The ID of the user.
     * @return \Illuminate\Http\JsonResponse A JSON response containing the portfolio and stock details.
     */
    public function getUserPortfoliosDetails($userId)
    {
        // Validate that the portfolio belongs to the user
        $portfolioExists = DB::table('user_portfolios')
            ->where('user_id', $userId)
            ->exists();

        if (!$portfolioExists) {
            return response()->json(['error' => 'Portfolio not found or does not belong to the user'], 404);
        }

        // Retrieve all stocks in the specified portfolio
        $stocks = DB::table('user_stock_portfolio')
            ->join('stock_symbols', 'user_stock_portfolio.stock_id', '=', 'stock_symbols.id')
            ->join('user_portfolios', 'user_stock_portfolio.portfolio_id', '=', 'user_portfolios.id')
            ->where('user_stock_portfolio.user_id', $userId)
            ->select(
                'stock_symbols.symbol',
                'user_portfolios.portfolio',
                'user_stock_portfolio.stock_id',
                'user_stock_portfolio.position',
                'user_stock_portfolio.avg_price',
                'user_stock_portfolio.cost_basis',
                'user_stock_portfolio.unrealized_pl',
                'user_stock_portfolio.daily_pl',
                'user_stock_portfolio.market_cap AS market_value',
                'user_stock_portfolio.updated_at'
            )
            ->get();

        return response()->json(['stocks' => $stocks]);
    }

    /**
     * Log the action taken on a portfolio by the user.
     *
     * @param int $userId The ID of the user.
     * @param string $action The action taken (e.g., 'create', 'update', 'delete').
     * @param int|null $portfolioId The ID of the portfolio (optional).
     * @return void
     */
    private function logPortfolioAction($userId, $action, $portfolioId = null)
    {
        $date = now()->toDateTimeString();
        DB::table('stock_user_portfolio_logger')->insert([
            'user_id' => $userId,
            'portfolio_id' => $portfolioId,
            'action_taken' => $action . " (Date: $date)",
            'created_at' => $date,
            'updated_at' => $date,
        ]);
    }

    /**
     * Get the portfolio action logs for a specific user.
     *
     * @param Request $request The HTTP request containing the user_id.
     * @return \Illuminate\Http\JsonResponse A JSON response containing the logs.
     */
    public function getUserPortfolioLogs(Request $request)
    {
        $userId = $request->input('user_id');
        if (!$userId) {
            return response()->json(['error' => 'User ID is required'], 400);
        }

        $logs = DB::table('stock_user_portfolio_logger')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['logs' => $logs]);
    }
}