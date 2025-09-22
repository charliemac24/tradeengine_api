<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockDividendQuarterly extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_dividend_quarterly';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'avg_dividend',
        'amount',
        'paydate',
        'from_date',
        'to_date'
    ];

    /**
     * Insert or update stock dividend quarterly at stock_dividend_quarterly table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockDividendQuarterly(int $stockId, array $data): bool
    {
        return (bool) self::updateOrCreate(
            [
                'stock_id' => $stockId,
                'from_date' => $data['from_date'],
                'to_date' => $data['to_date']
            ], // search criteria
            [
                'avg_dividend' => $data['avg_dividend'],
                'amount' => $data['amount'],
                'paydate' => $data['payDate']
            ]
        );
    }
}