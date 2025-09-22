<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockInsider extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_insiders';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'change',
        'filling_date',
        'executive',
        'share',
        'trans_price',
        'trans_val',
        'trans_code',
        'trans_date'
    ];

    /**
     * Insert or update stock insider at stock_insiders table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockInsider(int $stockId, array $data): bool
    {
            
        return (bool) self::updateOrCreate(
            [
                'stock_id' => $stockId,
                'executive' => $data['name'],
                'share' => $data['share'],
                'trans_code' => $data['transactionCode'],
            ], // search criteria
            [
                'change' => $data['change'],
                'filling_date' => $data['filingDate'],
                'trans_price' => $data['transactionPrice'],
                'trans_val' => (float) ( $data['share'] ?? 0 ) * (float) ( $data['transactionPrice'] ?? 0 ),
                'trans_code' => $data['transactionCode'],
                'trans_date' => $data['transactionDate']
            ]
        );
    }
}
