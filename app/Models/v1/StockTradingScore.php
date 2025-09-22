<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Model;

class StockTradingScore extends Model
{
    // Explicitly define the table name since it doesn't follow the plural naming convention.
    protected $table = 'stock_trading_score';

    // If your table does not have created_at and updated_at columns, disable timestamps.
    public $timestamps = false;

    // Optionally specify fillable fields if you need mass assignment.
    protected $fillable = ['symbol', 'technical_score', 'fundamental_score','news_sentiment_score','social_sentiment_score','analyst_score','trade_engine_score'];
    
    
    public static function createUpdateTradingScore($data)
    {

        if (!isset($data['symbol'])) {
            throw new \InvalidArgumentException('Stock symbol is required.');
        }
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        // Use updateOrCreate to either update the record or create a new one
        $record = self::updateOrCreate(
            ['symbol' => $data['symbol']], // Condition to check for an existing record
            [
                'technical_score'       => $data['technical_score'] ?? null,
                'fundamental_score'     => $data['fundamental_score'] ?? null,
                'news_sentiment_score'  => $data['news_sentiment_score'] ?? null,
                'social_sentiment_score'=> $data['social_sentiment_score'] ?? null,
                'analyst_score'         => $data['analyst_score'] ?? null,
                'trade_engine_score'    => $data['trade_engine_score'] ?? null,
            ]
        );
        if ($record->wasRecentlyCreated) {
    // New row inserted
    $affected = 1;
    $status = "inserted";
} elseif ($record->wasChanged()) {
    // Existing row updated with new values
    $affected = 1;
    $status = "updated";
} else {
    // No changes (values were the same)
    $affected = 0;
    $status = "no changes";
}

echo $affected . " row(s) " . $status . " for symbol " . $data['symbol'] . "\n";
        return $record;
    }

    
}
