<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockCandle extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_candle';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'close_price',
        'high_price',
        'low_price',
        'open_price',
        'response_status',
        'ts',
        'volume'
    ];

    /**
     * Insert or update stock candle at stock_candle table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockCandle(int $stockId, array $data): bool
    {       
        $timestamp = date('Y-m-d', $data['t']);

        return (bool) self::updateOrCreate(
            [
                'stock_id' => $stockId,
                'ts' => $timestamp
            ],
            [
                'close_price' => $data['c'],
                'high_price' => $data['h'],
                'low_price' => $data['l'],
                'open_price' => $data['o'],
                'response_status' => $data['s'] ?? 'unknown',
                'volume' => $data['v']
            ]
        );
    }
}
