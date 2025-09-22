<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockSocialSentiment extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_social_sentiments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'at_time',
        'mentions',
        'positive_score',
        'negative_score',
        'positive_mention',
        'negative_mention',
        'score'
    ];

    /**
     * Insert or update stock social sentiments at stock_social_sentiments table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockSocialSentiments(int $stockId, array $data): bool
    {
        return (bool) self::updateOrCreate(
            [
                'stock_id' => $stockId,
                'at_time' => $data['atTime']
            ], // search criteria
            [
                'at_time' => $data['atTime'],
                'mentions' => $data['mention'],
                'positive_score' => $data['positiveScore'],
                'negative_score' => $data['negativeScore'],
                'positive_mention' => $data['positiveMention'],
                'negative_mention' => $data['negativeMention'],
                'score' => $data['score']
            ]
        );
    }
        /**
 
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAllStockSocialSentiments(int $limit = 50, int $offset = 0) {
        return self::orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
    }
}
