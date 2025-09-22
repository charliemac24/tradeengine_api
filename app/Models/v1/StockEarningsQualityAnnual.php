<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockEarningsQualityAnnual extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_earnings_quality_annual';

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
        'score'
    ];

    /**
     * Insert or update stock earnings quality annual at stock_earnings_quality_annual table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockEarningsQualityAnnual(int $stockId, array $data): bool
    {
        return (bool) self::updateOrCreate(
            [
                'stock_id' => $stockId,
                'period' => $data['period']
            ],
            [
                'capitalAllocation' => $data['capitalAllocation'],
                'growth' => $data['growth'],
                'letterScore' => $data['letterScore'],
                'leverage' => $data['leverage'],
                'profitability' => $data['profitability'],
                'score' => $data['score']
            ]
        );
    }

    /**
     * Get earnings quality annual data by stock ID
     *
     * @param int $stockId
     * @return array
     */
    public static function getEarningsQualityAnnualByStockId(int $stockId)
    {
        return self::where('stock_id', $stockId)
            ->orderBy('period', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Retrieve the first 100 records from stock_earnings_quality_annual table with pagination.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAllEarningsQualityAnnual(int $limit = 100, int $offset = 0)
    {
        return self::orderBy('period', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
    }
}