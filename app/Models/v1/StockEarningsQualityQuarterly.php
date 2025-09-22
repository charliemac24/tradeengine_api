<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockEarningsQualityQuarterly extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_earnings_quality_quarterly';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'capitalAllocation',
        'growth',
        'letterScore',
        'leverage',
        'period',
        'profitability',
        'score',
        'prev_score'
    ];

    /**
     * Insert or update stock earnings quality quarterly at stock_earnings_quality_quarterly table.
     *
     * @param int $stockId
     * @param array $response
     * @return bool
     */
    public static function updateStockEarningsQualityQuarterly(int $stockId, array $response): bool
    {
        $data = $response['data'][0];

        // Retrieve the existing record for the given stock ID and period
        $existingRecord = self::where('stock_id', $stockId)
            ->where('period', $data['period'])
            ->first();

        // If a record exists, update the prev_score field with the current score
        if ($existingRecord) {
            $existingRecord->update([
                'prev_score' => $existingRecord->score,
            ]);
        }

        return (bool) self::updateOrCreate(
            [
                'stock_id' => $stockId,
                'period' => $data['period']
            ],
            [
                'capitalAllocation' => $data['cashGenerationCapitalAllocation'],
                'growth' => $data['growth'],
                'letterScore' => $data['letterScore'],
                'leverage' => $data['leverage'],
                'profitability' => $data['profitability'],
                'score' => $data['score']
            ]
        );
    }

    /**
     * Get earnings quality quarterly data by stock ID
     *
     * @param int $stockId
     * @return array
     */
    public static function getEarningsQualityQuarterlyByStockId(int $stockId)
    {
        return self::where('stock_id', $stockId)
            ->orderBy('period', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Retrieve the first 100 records from stock_earnings_quality_quarterly table with pagination.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAllEarningsQualityQuarterly(int $limit = 100, int $offset = 0)
    {
        return self::orderBy('period', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
    }
}