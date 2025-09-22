<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\v1\Stock;
use App\Models\v1\StockIndicator;
use App\Helpers\ExecutionTimer;
use App\Models\v1\StockTradingScore;
use App\Models\v1\StockEarningsQualityQuarterly;
use App\Models\v1\StockBasicFinancialMetric;
use App\Models\v1\StockNewsSentiment;
use App\Models\v1\StockSocialSentiment;
use App\Models\v1\StockRecommendationTrends;
use App\Models\v1\StockPriceTarget;
use App\Models\v1\StockEarningsCalendar;
use App\Models\v1\StockSectorMetrics;
use App\Models\v1\StockInfo;

class StockTradingScoreController extends Controller
{
    private $apiBaseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->apiBaseUrl = config('services.finnhub.base_url');
        $this->apiKey     = config('services.finnhub.key');
    }

    /**
     * Example endpoint to retrieve indicator data from stock_indicators table
     * and compute the technical score.
     */
    public function processStockTradingScore(Request $request)
    {
        // Retrieve symbol from request, e.g. "AAPL"
        $symbol = $request->input('symbol');

        // Get the stock record (assuming you have a 'stocks' table with a 'symbol' column)
        $stock = Stock::where('symbol', $symbol)->first();
        if (!$stock) {
            return response()->json(['error' => "Stock with symbol {$symbol} not found."], 404);
        }

        // Now retrieve the row from the stock_indicators table corresponding to this stock
        $indicator = StockIndicator::where('stock_id', $stock->id)->first();

        // Now retrieve the row from the quality score table
        $qualityScore = StockEarningsQualityQuarterly::where('stock_id', $stock->id)->first();

        // Basic financial metrics
        $basicFinancialMetrics = StockBasicFinancialMetric::where('stock_id', $stock->id)->first();

        // News Sentiment Score
        $newsSentiment = StockNewsSentiment::where('stock_id', $stock->id)->first();

        // Social Sentiment Score
        $socialSentiment = StockSocialSentiment::where('stock_id', $stock->id)->orderBy('at_time', 'desc')->first();

        $stockInfo = StockInfo::where('stock_id', $stock->id)->first();

        // Get the sector of the stock symbol
        $sector = $stockInfo->sector ?? null;
        if ($sector) {
            // Get the stock sector metrics data by sector
            $sectorMetrics = StockSectorMetrics::where('sector', $sector)->first();
        } else {
            $sectorMetrics = null;
        }

        // Recommendation trends
        $recommendation = StockRecommendationTrends::where('stock_id', $stock->id)->first();

        // Price target
        $priceTarget = StockPriceTarget::where('stock_id', $stock->id)->first();

        // Earnings calendar (most recent quarter or next quarter)
        $earnings = StockEarningsCalendar::where('stock_id', $stock->id)
            ->orderBy('cal_date', 'desc')
            ->first();

        // Map columns from 'stock_indicators' to local variables for technical score calculation
        $rsi            = $indicator->rsi ?? 0;
        $macdLine       = $indicator->macd ?? 0;
        $macdSignal     = $indicator->macd_signal_line ?? 0;
        $macdHistogram  = $indicator->macd_hist ?? 0;
        $currentPrice   = $indicator->price ?? 0;
        $ma50           = $indicator->sma_50 ?? 0;    // or $indicator->ema_50 if you prefer
        $ma200          = $indicator->sma_200 ?? 0;   // or $indicator->ema_200 if you prefer

        // For booleans: decide how you want to derive these based on your columns.
        $recentMacdCrossover = !empty($indicator->bullish) ? $indicator->bullish == 1 : null;
        $freshGoldenCross    = ($ma50 > $ma200);
        $adx                 = $indicator->adx ?? 0;
        $plusDI              = $indicator->plus_di ?? 0;
        $minusDI             = $indicator->minus_di ?? 0;

        // Add these lines to define the bands
        $upperBand  = $indicator->upperband ?? 0;
        $lowerBand  = $indicator->lower_b ?? 0;
        $middleBand = $indicator->middle_band ?? 0;

        // Calculate the technical score using your existing function
        $technicalScore = $this->calculateTechnicalScore(
            $rsi,
            $macdLine,
            $macdSignal,
            $macdHistogram,
            $recentMacdCrossover,
            $currentPrice,
            $ma50,
            $ma200,
            $freshGoldenCross,
            $adx,
            $plusDI,
            $minusDI,
            $upperBand,
            $lowerBand,
            $middleBand
        );

        // Fundamental Score Calculation
        $fundamentalScore = $this->processFundamentalScore($qualityScore, $basicFinancialMetrics, $sectorMetrics);

        // News Sentiment Score Calculation
        $newsSentimentScore = $this->processNewsSentimentScore($newsSentiment);

        // Social Sentiment Score Calculation
        $socialSentimentScore = $this->processSocialSentiment($socialSentiment);

        // Analyst Score Calculation
        $analystScore = $this->processAnalystScore($recommendation, $priceTarget, $earnings);

        // Put everything in an array for response or further logic
        $trade_engine_score = 0.20 * $technicalScore['totalScore']
                            + 0.25 * $fundamentalScore['totalScore']
                            + 0.15 * $newsSentimentScore['totalScore']
                            + 0.15 * $socialSentimentScore['totalScore']
                            + 0.25 * $analystScore['totalScore'];

        // Optionally, round the final score to 2 decimal places:
        $trade_engine_score = round($trade_engine_score, 2);

        $data = [
            'symbol'                  => $symbol,
            'technical_score'         => $technicalScore['totalScore'],
            'fundamental_score'       => $fundamentalScore['totalScore'],
            'news_sentiment_score'    => $newsSentimentScore['totalScore'],
            'social_sentiment_score'  => $socialSentimentScore['totalScore'],
            'analyst_score'           => $analystScore['totalScore'],
            'trade_engine_score'      => $trade_engine_score
        ];

        print_r($data);

        // Save the percentage here
        StockTradingScore::createUpdateTradingScore($data);

        // Update or insert into stock_fundamental_percentage table
        $result = DB::table('stock_fundamental_percentage')->updateOrInsert(
            ['stock_symbol' => $symbol], // Match on stock_symbol
            [
                'valuationPercentage'     => $fundamentalScore['valuationPercentage'],
                'growthPercentage'        => $fundamentalScore['growthPercentage'],
                'profitabilityPercentage' => $fundamentalScore['profitabilityPercentage'],
                'balanceSheetPercentage'  => $fundamentalScore['balanceSheetPercentage'],
                'earningsPercentage'      => $fundamentalScore['earningsPercentage']
            ]
        );

        
        
    }

    /**
     * Calculates the technical score based on various technical indicators.
     */
    public function calculateTechnicalScore(
        $rsi,
        $macdLine,
        $macdSignal,
        $macdHistogram,
        $recentMacdCrossover,
        $currentPrice,
        $ma50,
        $ma200,
        $freshGoldenCross,
        $adx,
        $plusDI,
        $minusDI,
        $upperBand = 0,
        $lowerBand = 0,
        $middleBand = 0
    ) {
        // RSI Score (custom formula)
        if ($rsi <= 50) {
            $rsiScore = 0;
        } elseif ($rsi <= 70) {
            $rsiScore = (($rsi - 50) / 20) * 20;
        } else {
            $rsiScore = 20 + min((($rsi - 70) / 15) * 10, 10);
        }

        // MACD & Histogram Score (new formula)
        $macdScore = 0;
        if ($macdLine > $macdSignal) {
            $macdScore += 10;
        }
        if ($macdHistogram > 0) {
            $macdScore += 10;
        }
        if ($recentMacdCrossover) {
            $macdScore += 10;
        }

        // Price vs SMA Score (0-30 pts) using your formula
        // SMA50 Score (0–10 pts)
        $sma50_score = 0;
        if ($ma50 > 0) {
            $sma50_score = max(0, min((($currentPrice - $ma50) / $ma50) * 200, 10));
        }

        // SMA200 Score (0–10 pts)
        $sma200_score = 0;
        if ($ma200 > 0) {
            $sma200_score = max(0, min((($currentPrice - $ma200) / $ma200) * 100, 10));
        }

        // Golden Cross Bonus (0 or 10 pts)
        $golden_cross_score = ($ma50 > $ma200) ? 10 : 0;

        $maPriceScore = $sma50_score + $sma200_score + $golden_cross_score;

        // Bollinger Band Position Score (0–30 pts)
        $bb_score = 0;
        if ($currentPrice > $upperBand) {
            $bb_score = 30;
        } elseif ($currentPrice >= $upperBand - ($upperBand - $lowerBand) * 0.33) {
            $bb_score = 20;
        } elseif ($currentPrice >= $middleBand) {
            $bb_score = 10;
        } else {
            $bb_score = 0;
        }

        // Calculate raw technical score
        $raw_technical_score = $rsiScore + $macdScore + $maPriceScore + $bb_score;

        // Cap technical score at 100
        $technical_score = min($raw_technical_score, 100);

        // ADX Score (max 25) - you can keep this for reporting, but it's not included in the technical score sum now
        $adxScore = 0;
        if ($adx > 25 && $plusDI > $minusDI) {
            $adxScore += 15;
        }
        if ($adx > 30) {
            $adxScore += 10;
        }
        if ($adxScore > 25) {
            $adxScore = 25;
        }

        // Return all scores
        return [
            'rsiScore'            => $rsiScore,
            'macdScore'           => $macdScore,
            'maPriceScore'        => $maPriceScore,
            'bb_score'            => $bb_score,
            'adxScore'            => $adxScore,
            'sma50_score'         => $sma50_score,
            'sma200_score'        => $sma200_score,
            'golden_cross_score'  => $golden_cross_score,
            'raw_technical_score' => $raw_technical_score,
            'totalScore'          => $technical_score,
        ];
    }

    /**
     * Endpoint to process fundamental score for a given stock.
     * Expects fundamental metrics via the request.
     */
    public function processFundamentalScore($qualityScore, $basicFinancialMetrics, $sectorMetrics)
    {
        // Valuation metrics
        $pe     = $basicFinancialMetrics->pettm ?? 0;
        $ps     = $basicFinancialMetrics->psttm ?? 0;
        $peg    = $basicFinancialMetrics->epsGrowthTTMYoy != 0 ? $pe / ($basicFinancialMetrics->epsGrowthTTMYoy ?? 0) : 0;
        $peSector  = $sectorMetrics->peTTM ?? 0;
        $psSector  = $sectorMetrics->psTTM ?? 0;
        $pegSector = ($sectorMetrics->epsGrowthTTMYoy ?? 0) != 0 ? $sectorMetrics->peTTM / $sectorMetrics->epsGrowthTTMYoy : 0;
        $ebitdaPerShareTTM     = $basicFinancialMetrics->ebitdaPerShareTTM ?? 0;

        // Growth metrics
        $revenueGrowth      = $basicFinancialMetrics->revenueGrowthTTMYoy ?? 0;
        $epsGrowth          = $basicFinancialMetrics->epsGrowthTTMYoy ?? 0;
        $revenueGrowthSector = $sectorMetrics->revenueGrowthTTMYoy ?? 0;
        $epsGrowthSector     = $sectorMetrics->epsGrowthQuarterlyYoy ?? 0;

        // Profitability metrics
        $netMargin        = $basicFinancialMetrics->netmargin ?? 0;
        $roe              = $basicFinancialMetrics->roettm ?? 0;
        $netMarginSector  = $sectorMetrics->netMarginGrowth5Y ?? 0;
        $roeSector        = $sectorMetrics->roeTTM ?? 0;

        // Balance Sheet Strength metric
        $debtToEquity      = $basicFinancialMetrics->totaldebt_totalequityquarterly ?? 0;
        $debtToEquitySector = $sectorMetrics->{'totalDebt/totalEquityAnnual'} ?? 0;

        // Earnings Quality metric
        $earningsQuality  = $qualityScore->letterScore ?? ""; // Expected letter grade (A, B, C, D, F)

        // Calculate the fundamental score
        $fundamentalScoreData = $this->calculateFundamentalScore(
            $pe, $ps, $peg, $peSector, $psSector, $pegSector,
            $revenueGrowth, $epsGrowth, $revenueGrowthSector, $epsGrowthSector,
            $netMargin, $roe, $netMarginSector, $roeSector,
            $debtToEquity, $debtToEquitySector, $earningsQuality, $ebitdaPerShareTTM
        );

        return [
            'totalScore' => $fundamentalScoreData['totalFundamentalScore'],
            'valuationPercentage'     => $fundamentalScoreData['valuationPercentage'],
            'growthPercentage'        => $fundamentalScoreData['growthPercentage'],
            'profitabilityPercentage' => $fundamentalScoreData['profitabilityPercentage'],
            'balanceSheetPercentage'  => $fundamentalScoreData['balanceSheetPercentage'],
            'earningsPercentage'      => $fundamentalScoreData['earningsPercentage'],
        ];
    }

    /**
     * Calculates the Fundamental Score based on several key categories.
     * Maximum points per category:
     *   - Valuation: 25 points
     *   - Growth: 20 points
     *   - Profitability: 20 points
     *   - Balance Sheet Strength: 10 points
     *   - Earnings Quality: 25 points
     * Total maximum score: 100 points.
     */
    public function calculateFundamentalScore(
        $pe,
        $ps,
        $peg,
        $peSector,
        $psSector,
        $pegSector,
        $revenueGrowth,
        $epsGrowth,
        $revenueGrowthSector,
        $epsGrowthSector,
        $netMargin,
        $roe,
        $netMarginSector,
        $roeSector,
        $debtToEquity,
        $debtToEquitySector,
        $earningsQuality,
        $ev_ebitda = 0 // Add this parameter if not already present
    ) {
        // 1. Valuation (max 30 points)
        // P/E Score (0–10 pts)
        if ($pe <= 10) {
            $pe_score = 10;
        } elseif ($pe <= 25) {
            $pe_score = ((25 - $pe) / 15) * 10;
        } else {
            $pe_score = 0;
        }

        // P/S Score (0–10 pts)
        if ($ps <= 2) {
            $ps_score = 10;
        } elseif ($ps <= 5) {
            $ps_score = ((5 - $ps) / 3) * 10;
        } else {
            $ps_score = 0;
        }

        // EV/EBITDA Score (0–10 pts)
        if ($ev_ebitda <= 10) {
            $ev_score = 10;
        } elseif ($ev_ebitda <= 20) {
            $ev_score = ((20 - $ev_ebitda) / 10) * 10;
        } else {
            $ev_score = 0;
        }

        $valuationScore = $pe_score + $ps_score + $ev_score;

        // 2. Growth Score (0–30 pts)
        // Revenue YoY Growth Score (0–15 pts)
        if ($revenueGrowth >= 30) {
            $rev_score = 15;
        } elseif ($revenueGrowth >= 5) {
            $rev_score = (($revenueGrowth - 5) / 25) * 15;
        } else {
            $rev_score = 0;
        }

        // EPS 3-Year CAGR Score (0–15 pts)
        if ($epsGrowth >= 20) {
            $eps_score = 15;
        } elseif ($epsGrowth >= 5) {
            $eps_score = (($epsGrowth - 5) / 25) * 15;
        } else {
            $eps_score = 0;
        }

        $growthScore = $rev_score + $eps_score;

        // 3. Profitability Score (0–30 pts)
        // Net Margin Score (0–10 pts)
        if ($netMargin >= 20) {
            $nm_score = 10;
        } elseif ($netMargin >= 5) {
            $nm_score = (($netMargin - 5) / 15) * 10;
        } else {
            $nm_score = 0;
        }

        // ROE Score (0–10 pts)
        if ($roe >= 25) {
            $roe_score = 10;
        } elseif ($roe >= 5) {
            $roe_score = (($roe - 5) / 20) * 10;
        } else {
            $roe_score = 0;
        }

        // Gross Margin Score (0–10 pts)
        $grossMargin = $basicFinancialMetrics->grossmargin ?? 0;
        if ($grossMargin >= 50) {
            $gm_score = 10;
        } elseif ($grossMargin >= 20) {
            $gm_score = (($grossMargin - 20) / 30) * 10;
        } else {
            $gm_score = 0;
        }

        $profitabilityScore = $nm_score + $roe_score + $gm_score;

        // 4. Balance Sheet Strength (max 10 points)
        // Debt/Equity Score (0–10 pts)
        if ($debtToEquity <= 1) {
            $de_score = 10;
        } elseif ($debtToEquity <= 2) {
            $de_score = ((2 - $debtToEquity) / 1) * 10;
        } else {
            $de_score = 0;
        }

        // 5. Earnings Quality (max 25 points)
        $qualityScoreMapping = [
            'A' => 25,
            'A-' => 22,
            'B+' => 20,
            'B' => 17,
            'B-' => 15,
            'C+' => 12,
            'C' => 10,
            'C-' => 7,
            'D' => 5,
            'F' => 0
        ];

        $earningsQualityScore = $qualityScoreMapping[strtoupper(trim($earningsQuality))] ?? 0;

        // Sum up all the scores
        $totalFundamentalScore = $valuationScore + $growthScore + $profitabilityScore + $de_score + $earningsQualityScore;

        return [
            'totalFundamentalScore'   => $totalFundamentalScore,
            'valuationPercentage'     => ($valuationScore / 25) * 100,
            'growthPercentage'        => ($growthScore / 20) * 100,
            'profitabilityPercentage' => ($profitabilityScore / 20) * 100,
            'balanceSheetPercentage'  => ($de_score / 10) * 100,
            'earningsPercentage'      => ($earningsQualityScore / 25) * 100,
        ];
    }

    private function calculateComponentPercentages(
        $valuationScore,
        $growthScore,
        $profitabilityScore,
        $balanceSheetScore,
        $earningsScore
    ) {
        return [
            'valuationPercentage'     => ($valuationScore / 25) * 100,
            'growthPercentage'        => ($growthScore / 20) * 100,
            'profitabilityPercentage' => ($profitabilityScore / 20) * 100,
            'balanceSheetPercentage'  => ($balanceSheetScore / 10) * 100,
            'earningsPercentage'      => ($earningsScore / 25) * 100,
        ];
    }

    public function processNewsSentimentScore($news)
    {
        // Map database columns to local variables
        $companyNewsScore = $news->companynews_score ?? 0;     // decimal(0–1) from the table
        $bullishPercent   = $news->sentiment_bullish ?? 0;     // decimal(0–100) or (0–1), depending on your data
        $bearishPercent   = $news->sentiment_bearish ?? 0;     // decimal(0–100) or (0–1)

        // If your table has a column for sector average, retrieve it; otherwise, you might set it from the request or another table
        $sectorAvgNewsScore = 0.5; // for example, or fetch from a config or another table

        // Calculate the News Sentiment Score
        $newsScoreData = $this->calculateNewsSentimentScore($companyNewsScore, $bullishPercent, $bearishPercent, $sectorAvgNewsScore);

        return [
            'totalScore' => $newsScoreData['totalNewsSentimentScore'],
        ];
    }

    public function calculateNewsSentimentScore(
        float $companyNewsScore,       // Range: 0–1
        float $bullishPercent,         // Could be 0–100 or 0–1
        float $bearishPercent,         // Could be 0–100 or 0–1
        float $sectorAvgNewsScore      // Range: 0–1 (optionally used)
    ) {
        // 1. Convert companyNewsScore to a base points out of 100
        $baseScore = $companyNewsScore * 100; // If 0.0 - 1.0, becomes 0 - 100

        // 2. Optional: If sectorAvgNewsScore is relevant, adjust the base score
        if ($companyNewsScore > $sectorAvgNewsScore) {
            $baseScore += 5; // small bump
        }

        // 3. Check bullish vs. bearish percentages
        if ($bullishPercent > 60) {
            $baseScore += 10;
        }

        // Optionally, if coverage is extremely low or high (sum of bullish + bearish?), adjust
        $coverageTotal = $bullishPercent + $bearishPercent;
        if ($coverageTotal < 20) {
            // Very little coverage => reduce confidence
            $baseScore -= 5;
        }

        // 4. Cap or floor the final score at 0–100
        $finalScore = max(0, min(100, $baseScore));

        return [
            'baseScore'    => $baseScore,
            'coverage'     => $coverageTotal,
            'totalNewsSentimentScore'   => $finalScore,
        ];
    }

    public function processSocialSentiment($social)
    {
        $positiveMentions = !empty($social->positive_mention) ? $social->positive_mention : 0; // int
        $negativeMentions = !empty($social->negative_mention) ? $social->negative_mention : 0; // int
        $totalMentions    = !empty($social->mentions) ? $social->mentions : 0;          // int
        $positiveScore    = !empty($social->positive_score) ? $social->positive_score : 0;   // decimal
        $negativeScore    = !empty($social->negative_score) ? $social->negative_score : 0;   // decimal
        $score            = !empty($social->score) ? $social->score : 0;            // decimal

        // Compute the social sentiment score (0–100)
        $result = $this->calculateSocialSentiment($positiveMentions, $negativeMentions, $totalMentions);

        return [
            'totalScore' => $result
        ];
    }

    public function calculateSocialSentiment(int $positive, int $negative, int $total)
    {
        // Avoid division by zero
        if ($total <= 0) {
            // If no mentions, you might default to 50 or 0 or some neutral score
            return 50;
        }

        // 1. Raw sentiment: -1 (all negative) to +1 (all positive)
        $rawSentiment = ($positive - $negative) / $total;

        // 2. Scale -1..+1 to 0..100
        //   -1 => 0, 0 => 50, +1 => 100
        $finalScore = ($rawSentiment + 1) * 50;

        // You can round, floor, or cast to int as needed:
        $finalScore = max(0, min(100, $finalScore));

        return round($finalScore, 2);  // e.g., keep 2 decimals
    }

    public function processAnalystScore($recommendation, $priceTarget, $earnings)
    {
        // Gather relevant data
        $buy       = !empty($recommendation->buy) ? $recommendation->buy : 0;
        $strongBuy = !empty($recommendation->strongBuy) ? $recommendation->strongBuy : 0;
        $sell      = !empty($recommendation->sell) ? $recommendation->sell : 0;
        $strongSell= !empty($recommendation->strongSell) ? $recommendation->strongSell : 0;
        $hold      = !empty($recommendation->hold) ? $recommendation->hold : 0;

        $totalRatings  = $buy + $strongBuy + $sell + $strongSell + $hold;

        // Price target
        $targetMedian  = $priceTarget->target_median ?? 0;
        $currentPrice  = $stock->current_price ?? 100.0; // example fallback

        // Earnings
        $epsEstimate   = $earnings->eps_estimate ?? 0;
        $epsActual     = $earnings->eps_actual ?? 0;

        // Compute the score
        $analystScoreData = $this->calculateAnalystScore(
            $buy,
            $strongBuy,
            $sell,
            $strongSell,
            $hold,
            $totalRatings,
            $targetMedian,
            $currentPrice,
            $epsEstimate,
            $epsActual
        );

        return [
            'totalScore'  => $analystScoreData['finalScore']
        ];
    }

    public function calculateAnalystScore(
        int $buy,
        int $strongBuy,
        int $sell,
        int $strongSell,
        int $hold,
        int $totalRatings,
        float $targetMedian,
        float $currentPrice,
        float $epsEstimate,
        float $epsActual
    ) {
        // 1. Recommendation Score (max 15)
        $recommendationScore = 0;
        if ($totalRatings > 0) {
            $positive = $buy + $strongBuy;
            $ratio = ($positive / $totalRatings) * 100; // in percentage
            if ($ratio >= 70) {
                $recommendationScore = 15;
            } elseif ($ratio >= 50) {
                $recommendationScore = 10;
            } else {
                $recommendationScore = 0;
            }
        }

        // 2. Price Target Score (max 10)
        $priceTargetScore = 0;
        $targetMultiple = ($currentPrice > 0) ? ($targetMedian / $currentPrice) : 1;
        if ($targetMultiple >= 1.2) {
            $priceTargetScore = 10;
        } elseif ($targetMultiple >= 1.1) {
            $priceTargetScore = 5;
        }

        // 3. Earnings Revision / Surprise Score (max 10)
        $earningsScore = 0;
        if ($epsActual > $epsEstimate) {
            $earningsScore = 10;
        } elseif (abs($epsActual - $epsEstimate) <= 0.01) {
            $earningsScore = 5;
        }

        // 4. Sum partial scores
        $subtotal = $recommendationScore + $priceTargetScore + $earningsScore;

        // 5. Convert to 0–100 scale
        $final = ($subtotal / 35) * 100;

        // Bound the score at 0–100
        $finalScore = max(0, min(100, $final));

        return [
            'recommendationScore' => $recommendationScore,
            'priceTargetScore'    => $priceTargetScore,
            'earningsScore'       => $earningsScore,
            'finalScore'          => round($finalScore, 2),
        ];
    }

    public function getFundamentalPercentage(Request $request)
    {
        $stockSymbol = $request->input('symbol');

        // Fetch the fundamental percentages for the given stock symbol
        $fundamentalData = DB::table('stock_fundamental_percentage')
            ->where('stock_symbol', $stockSymbol)
            ->select(
                'valuationPercentage',
                'growthPercentage',
                'profitabilityPercentage',
                'balanceSheetPercentage',
                'earningsPercentage'
            )
            ->first();

        if (!$fundamentalData) {
            return response()->json(['error' => "No data found for stock symbol {$stockSymbol}"], 404);
        }

        return response()->json($fundamentalData);
    }
}

