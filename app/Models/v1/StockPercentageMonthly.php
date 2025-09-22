<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockPercentageMonthly extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_percentage_monthly';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'percentage',
        'closing_date'
    ];

    /**
     * Insert or update stock percentage at stock_percentage_monthly table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockPercentage(int $stockId, array $data): bool
    {       
          return (bool) self::updateOrCreate(
            [
                'stock_id' => $stockId,
                'closing_date' => $data['closing_date']
            ], // search criteria
            [
                'percentage' => $data['percentage'],
                'closing_date' => $data['closing_date']
            ]
        );
    }
}
