<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Model;

class StockIncomingArticles extends Model
{
    // If your table name does not match the pluralized form of the model name,
    // you can explicitly set the table name:
    protected $table = 'stock_incoming_articles';

    // Disable created_at and updated_at columns since you don't want timestamps.
    public $timestamps = false;

    // Define which attributes can be mass assigned.
    protected $fillable = [
        'title',
        'description',
        'featured_image',
        'date_created',
    ];
}
