<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockSentiment extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_news_sentiments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'sentiment_bearish',
        'sentiment_bullish',
        'sentiment',
        'companynews_score',
        'prev_companynews_score'
    ];

    /**
     * Insert or update stock news sentiments at stock_news_sentiments table.
     *
     * @param int $stockId
     * @param array $data
     * @param string $sentiment_label
     * @return bool
     */
    public static function updateStockNewsSentiments(int $stockId, array $data, string $sentiment_label): bool
    {
        // Retrieve the existing record for the given stock ID
        $existingRecord = self::where('stock_id', $stockId)->first();

        // If a record exists, update the prev_companynews_score with the current companynews_score
        if ($existingRecord) {
            $existingRecord->update([
                'prev_companynews_score' => $existingRecord->companynews_score,
            ]);
        }

        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId], // search criteria
            [
                'sentiment_bearish' => $data['sentiment']['bearishPercent'],
                'sentiment_bullish' => $data['sentiment']['bullishPercent'],
                'sentiment' => $sentiment_label,
                'companynews_score' => $data['companyNewsScore']
            ]
        );
    }
}
