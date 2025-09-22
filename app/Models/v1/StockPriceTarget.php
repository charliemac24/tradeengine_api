<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockPriceTarget extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_price_target';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'last_updated_source',
        'number_analysts',
        'symbol',
        'target_high',
        'target_low',
        'target_mean',
        'target_median',
        'prev_target_median'
    ];

    /**
     * Insert or update stock price target in the stock_price_target table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockPriceTarget(int $stockId, array $data): bool
    {
        // Retrieve the existing record for the given stock ID
        $existingRecord = self::where('stock_id', $stockId)->first();

        // If a record exists, update the prev_target_median with the current target_median
        if ($existingRecord) {
            $existingRecord->update([
                'prev_target_median' => $existingRecord->target_median,
            ]);
        }

        // Update or create the record with the new data
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId], // search criteria
            [
                'last_updated_source' => $data['lastUpdated'],
                'number_analysts' => $data['numberAnalysts'],
                'symbol' => $data['symbol'],
                'target_high' => $data['targetHigh'],
                'target_low' => $data['targetLow'],
                'target_mean' => $data['targetMean'],
                'target_median' => $data['targetMedian']
            ]
        );
    }

}
