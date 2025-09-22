<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockCompanyEarningsQualityScore extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_company_earnings_quality_score';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'cashGenerationCapitalAllocation',
        'growth',
        'letterScore',
        'leverage',
        'period',
        'profitability',
        'score',
        'prev_score'
    ];

    /**
     * Insert or update stock company earnings quality score.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockCompanyEarningsQualityScore(int $stockId, array $data): bool
    {

        // Retrieve the existing record for the given stock ID
        $existingRecord = self::where('stock_id', $stockId)->first();

        // If a record exists, update the prev_target_median with the current target_median
        if ($existingRecord) {
            $existingRecord->update([
                'prev_score' => $existingRecord->score,
            ]);
        }

        return (bool) self::updateOrCreate(
            [
                'stock_id' => $stockId,
                'period' => $data['period']
            ], // search criteria
            [
                'cashGenerationCapitalAllocation' => $data['cashGenerationCapitalAllocation'],
                'growth' => $data['growth'],
                'letterScore' => $data['letterScore'],
                'leverage' => $data['leverage'],
                'profitability' => $data['profitability'],
                'score' => $data['score']
            ]
        );
    }
}