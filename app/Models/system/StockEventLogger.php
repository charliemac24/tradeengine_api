<?php

namespace App\Models\system;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockEventLogger extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_event_log';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'message',
        'message_hash',
        'created_at'
    ];

    /**
     * Log a stock event.
     *
     * @param int $stockId
     * @param string $message
     * @return bool
     */
    public static function logEvent(int $stockId, string $message): bool
    {
        $hash = hash('sha256', $message);

        // Check if a log with this hash already exists for this stock
        if (self::where('stock_id', $stockId)->where('message_hash', $hash)->exists()) {
            return false;
        }

        return self::create([
            'stock_id' => $stockId,
            'message' => $message,
            'message_hash' => $hash,
            'created_at' => now()
        ]) ? true : false;
    }
}