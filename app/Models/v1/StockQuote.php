<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockQuote extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_quote';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'current_price',
        'high_price',
        'low_price',
        'open_price',
        'previous_close_price',
        'difference',
        'total_percentage',
        'ts'
    ];

    /**
     * Insert or update stock quote at stock_quote table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockQuote(int $stockId, array $data): bool
    {       
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId], // search criteria
            [
                'current_price' => $data['c'],
                'high_price' => $data['h'],
                'low_price' => $data['l'],
                'open_price' => $data['o'],
                'previous_close_price' => $data['pc'],
                'difference' => $data['c'] - $data['pc'],
                'total_percentage' => (($data['c'] - $data['pc']) / $data['pc']) * 100,
                'ts' => date('Y-m-d H:i:s',$data['t'])
            ]
        );
    }

    /**
     * Retrieve data from the stock_quote table where stock_id is equal to the given parameter.
     *
     * @param int $stockId
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getQuotesByStockId(int $stockId)
    {
        return self::where('stock_id', $stockId)
            ->select('stock_id', 'current_price', 'high_price', 'low_price', 'open_price', 'previous_close_price', 'difference', 'total_percentage', 'ts')
            ->orderBy('ts', 'desc')
            ->get();
    }

}
