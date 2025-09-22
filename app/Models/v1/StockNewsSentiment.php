<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockNewsSentiment extends Model
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
        'companynews_score'
    ];

    /**
     * Get news sentiment data for a specific stock ID.
     *
     * @param int $stockId
     * @return array|null
     */
    public static function getNewsSentimentByStockId(int $stockId): ?array
    {
        return self::where('stock_id', $stockId)
            ->join('stock_symbols', 'stock_news_sentiments.stock_id', '=', 'stock_symbols.id')
            ->first(['stock_news_sentiments.*', 'stock_symbols.symbol'])
            ->toArray();
    }

    /**
     * Get all news sentiments with optional pagination.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAllNewsSentiments(int $limit = 50, int $offset = 0): array
    {
        return self::join('stock_symbols', 'stock_news_sentiments.stock_id', '=', 'stock_symbols.id')
            ->orderBy('stock_news_sentiments.updated_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get(['stock_news_sentiments.*', 'stock_symbols.symbol'])
            ->toArray();
    }
} 