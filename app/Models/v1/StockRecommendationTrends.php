<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockRecommendationTrends extends Model
{
    use HasFactory;
    
    public $timestamps = false;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_recommendation_trends';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'buy',
        'hold',
        'period',
        'sell',
        'strongBuy',
        'strongSell'
    ];

    /**
     * Insert or update stock recommendation trends at stock_recommendation_trends table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateRecommendationTrends(int $stockId, array $data): bool
    {
        return (bool) self::updateOrCreate(
            [
                'stock_id' => $stockId,
                'period' => $data['period']
            ], // search criteria
            [
                'buy' => $data['buy'],
                'hold' => $data['hold'],
                'sell' => $data['sell'],
                'strongBuy' => $data['strongBuy'],
                'strongSell' => $data['strongSell']
            ]
        );
    }

    /**
     * Get recommendation trends for a specific stock ID.
     *
     * @param int $stockId
     * @return array
     */
    public static function getRecommendationTrendsByStockId(int $stockId): array
    {
        return self::where('stock_id', $stockId)
            ->join('stock_symbols', 'stock_recommendation_trends.stock_id', '=', 'stock_symbols.id')
            ->get(['stock_recommendation_trends.*', 'stock_symbols.symbol'])
            ->toArray();
    }

    /**
     * Get all recommendation trends with optional pagination.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAllRecommendationTrends(int $limit = 50, int $offset = 0): array
    {
        return self::join('stock_symbols', 'stock_recommendation_trends.stock_id', '=', 'stock_symbols.id')
            ->limit($limit)
            ->offset($offset)
            ->get(['stock_recommendation_trends.*', 'stock_symbols.symbol'])
            ->toArray();
    }
}