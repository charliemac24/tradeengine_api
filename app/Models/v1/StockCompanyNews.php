<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockCompanyNews extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_company_news';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'category',
        'date_time',
        'headline',
        'news_id',
        'image_url',
        'related',
        'source',
        'summary',
        'url'
    ];

    /**
     * Insert or update stock company newsc at stock_company_news table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockCompanyNews(int $stockId, array $data): bool
    {       
        // Check if the news item already exists
        $existingNews = self::where('news_id', $data['id'])->first();

        if ($existingNews) {
            // If the news item exists, update it
            return (bool) $existingNews->update([
                'category' => $data['category'],
                'date_time' => date('Y-m-d H:i:s', $data['datetime']),
                'headline' => $data['headline'],
                'image_url' => $data['image'],
                'related' => $data['related'],
                'source' => $data['source'],
                'summary' => $data['summary'],
                'url' => $data['url']
            ]);
        } else {
            // If the news item does not exist, create a new one
            return (bool) self::create([
                'stock_id' => $stockId,
                'category' => $data['category'],
                'date_time' => date('Y-m-d H:i:s', $data['datetime']),
                'headline' => $data['headline'],
                'news_id' => $data['id'],
                'image_url' => $data['image'],
                'related' => $data['related'],
                'source' => $data['source'],
                'summary' => $data['summary'],
                'url' => $data['url']
            ]);
        }
    }
}
