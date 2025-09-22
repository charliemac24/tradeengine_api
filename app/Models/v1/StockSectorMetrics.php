<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockSectorMetrics extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_sector_metrics';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sector',
        'assetTurnoverAnnual',
        'assetTurnoverTTM',
        'bookValueShareGrowth5Y',
        'currentDividendYieldTTM',
        'currentEv/freeCashFlowAnnual',
        'currentEv/freeCashFlowTTM',
        'currentRatioAnnual',
        'currentRatioQuarterly',
        'dividendGrowthRate5Y',
        'dividendYieldIndicatedAnnual',
        'ebitdaCagr5Y',
        'ebitdaInterimCagr5Y',
        'epsGrowth3Y',
        'epsGrowth5Y',
        'epsGrowthQuarterlyYoy',
        'epsGrowthTTMYoy',
        'focfCagr5Y',
        'grossMarginAnnual',
        'grossMarginTTM',
        'inventoryTurnoverAnnual',
        'inventoryTurnoverTTM',
        'longTermDebt/equityAnnual',
        'longTermDebt/equityQuarterly',
        'netDebtAnnual',
        'netDebtQuarterly',
        'netIncomeEmployeeAnnual',
        'netIncomeEmployeeTTM',
        'netInterestCoverageAnnual',
        'netInterestCoverageTTM',
        'netMarginGrowth5Y',
        'netProfitMarginAnnual',
        'netProfitMarginTTM',
        'operatingMarginAnnual',
        'operatingMarginTTM',
        'payoutRatioAnnual',
        'payoutRatioTTM',
        'pbAnnual',
        'pbQuarterly',
        'pcfShareTTM',
        'peAnnual',
        'peTTM',
        'pfcfShareAnnual',
        'pfcfShareTTM',
        'pretaxMarginAnnual',
        'pretaxMarginTTM',
        'psAnnual',
        'psTTM',
        'ptbvAnnual',
        'ptbvQuarterly',
        'quickRatioAnnual',
        'receivablesTurnoverAnnual',
        'receivablesTurnoverTTM',
        'revenueEmployeeAnnual',
        'revenueEmployeeTTM',
        'revenueGrowth3Y',
        'revenueGrowth5Y',
        'revenueGrowthQuarterlyYoy',
        'revenueGrowthTTMYoy',
        'revenueShareGrowth5Y',
        'roaRfy',
        'roaeTTM',
        'roeRfy',
        'roeTTM',
        'roiAnnual',
        'roiTTM',
        'tbvCagr5Y',
        'totalDebt/totalEquityAnnual',
        'totalDebt/totalEquityQuarterly',
    ];

    /**
     * Update or create stock sector metrics.
     *
     * @param array $data
     * @return bool
     */
    public static function updateStockSectorMetrics(array $data): bool
    {
        return (bool) self::updateOrCreate(
            ['sector' => $data['sector']], // Match by sector
            [
                'assetTurnoverAnnual' => $data['metrics']['assetTurnoverAnnual']['m'],
                'assetTurnoverTTM' => $data['metrics']['assetTurnoverTTM']['m'],
                'bookValueShareGrowth5Y' => $data['metrics']['bookValueShareGrowth5Y']['m'],
                'currentDividendYieldTTM' => $data['metrics']['currentDividendYieldTTM']['m'],
                'currentEv/freeCashFlowAnnual' => $data['metrics']['currentEv/freeCashFlowAnnual']['m'],
                'currentEv/freeCashFlowTTM' => $data['metrics']['currentEv/freeCashFlowTTM']['m'],
                'currentRatioAnnual' => $data['metrics']['currentRatioAnnual']['m'],
                'currentRatioQuarterly' => $data['metrics']['currentRatioQuarterly']['m'],
                'dividendGrowthRate5Y' => $data['metrics']['dividendGrowthRate5Y']['m'],
                'dividendYieldIndicatedAnnual' => $data['metrics']['dividendYieldIndicatedAnnual']['m'],
                'ebitdaCagr5Y' => $data['metrics']['ebitdaCagr5Y']['m'],
                'ebitdaInterimCagr5Y' => $data['metrics']['ebitdaInterimCagr5Y']['m'],
                'epsGrowth3Y' => $data['metrics']['epsGrowth3Y']['m'],
                'epsGrowth5Y' => $data['metrics']['epsGrowth5Y']['m'],
                'epsGrowthQuarterlyYoy' => $data['metrics']['epsGrowthQuarterlyYoy']['m'],
                'epsGrowthTTMYoy' => $data['metrics']['epsGrowthTTMYoy']['m'],
                'focfCagr5Y' => $data['metrics']['focfCagr5Y']['m'],
                'grossMarginAnnual' => $data['metrics']['grossMarginAnnual']['m'],
                'grossMarginTTM' => $data['metrics']['grossMarginTTM']['m'],
                'inventoryTurnoverAnnual' => $data['metrics']['inventoryTurnoverAnnual']['m'],
                'inventoryTurnoverTTM' => $data['metrics']['inventoryTurnoverTTM']['m'],
                'longTermDebt/equityAnnual' => $data['metrics']['longTermDebt/equityAnnual']['m'],
                'longTermDebt/equityQuarterly' => $data['metrics']['longTermDebt/equityQuarterly']['m'],
                'netDebtAnnual' => $data['metrics']['netDebtAnnual']['m'],
                'netDebtQuarterly' => $data['metrics']['netDebtQuarterly']['m'],
                'netIncomeEmployeeAnnual' => $data['metrics']['netIncomeEmployeeAnnual']['m'],
                'netIncomeEmployeeTTM' => $data['metrics']['netIncomeEmployeeTTM']['m'],
                'netInterestCoverageAnnual' => $data['metrics']['netInterestCoverageAnnual']['m'],
                'netInterestCoverageTTM' => $data['metrics']['netInterestCoverageTTM']['m'],
                'netMarginGrowth5Y' => $data['metrics']['netMarginGrowth5Y']['m'],
                'netProfitMarginAnnual' => $data['metrics']['netProfitMarginAnnual']['m'],
                'netProfitMarginTTM' => $data['metrics']['netProfitMarginTTM']['m'],
                'operatingMarginAnnual' => $data['metrics']['operatingMarginAnnual']['m'],
                'operatingMarginTTM' => $data['metrics']['operatingMarginTTM']['m'],
                'payoutRatioAnnual' => $data['metrics']['payoutRatioAnnual']['m'],
                'payoutRatioTTM' => $data['metrics']['payoutRatioTTM']['m'],
                'pbAnnual' => $data['metrics']['pbAnnual']['m'],
                'pbQuarterly' => $data['metrics']['pbQuarterly']['m'],
                'pcfShareTTM' => $data['metrics']['pcfShareTTM']['m'],
                'peAnnual' => $data['metrics']['peAnnual']['m'],
                'peTTM' => $data['metrics']['peTTM']['m'],
                'pfcfShareAnnual' => $data['metrics']['pfcfShareAnnual']['m'],
                'pfcfShareTTM' => $data['metrics']['pfcfShareTTM']['m'],
                'pretaxMarginAnnual' => $data['metrics']['pretaxMarginAnnual']['m'],
                'pretaxMarginTTM' => $data['metrics']['pretaxMarginTTM']['m'],
                'psAnnual' => $data['metrics']['psAnnual']['m'],
                'psTTM' => $data['metrics']['psTTM']['m'],
                'ptbvAnnual' => $data['metrics']['ptbvAnnual']['m'],
                'ptbvQuarterly' => $data['metrics']['ptbvQuarterly']['m'],
                'quickRatioAnnual' => $data['metrics']['quickRatioAnnual']['m'],
                'receivablesTurnoverAnnual' => $data['metrics']['receivablesTurnoverAnnual']['m'],
                'receivablesTurnoverTTM' => $data['metrics']['receivablesTurnoverTTM']['m'],
                'revenueEmployeeAnnual' => $data['metrics']['revenueEmployeeAnnual']['m'],
                'revenueEmployeeTTM' => $data['metrics']['revenueEmployeeTTM']['m'],
                'revenueGrowth3Y' => $data['metrics']['revenueGrowth3Y']['m'],
                'revenueGrowth5Y' => $data['metrics']['revenueGrowth5Y']['m'],
                'revenueGrowthQuarterlyYoy' => $data['metrics']['revenueGrowthQuarterlyYoy']['m'],
                'revenueGrowthTTMYoy' => $data['metrics']['revenueGrowthTTMYoy']['m'],
                'revenueShareGrowth5Y' => $data['metrics']['revenueShareGrowth5Y']['m'],
                'roaRfy' => $data['metrics']['roaRfy']['m'],
                'roaeTTM' => $data['metrics']['roaeTTM']['m'],
                'roeRfy' => $data['metrics']['roeRfy']['m'],
                'roeTTM' => $data['metrics']['roeTTM']['m'],
                'roiAnnual' => $data['metrics']['roiAnnual']['m'],
                'roiTTM' => $data['metrics']['roiTTM']['m'],
                'tbvCagr5Y' => $data['metrics']['tbvCagr5Y']['m'],
                'totalDebt/totalEquityAnnual' => $data['metrics']['totalDebt/totalEquityAnnual']['m'],
                'totalDebt/totalEquityQuarterly' => $data['metrics']['totalDebt/totalEquityQuarterly']['m'],
            ]
        );
    }
}