<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Model;

class StockTopNews extends Model
{
    protected $table = 'stock_top_news';
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
        'url',
    ];
}
