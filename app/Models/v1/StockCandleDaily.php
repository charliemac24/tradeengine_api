<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockCandleDaily extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_candle_daily';

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
                'response_status' => $data['s'],
                'volume' => $data['v']
            ]
        );
    }

    /**
     * Retrieve data from the stock_candle_daily table where stock_id is equal to the given parameter.
     *
     * @param int $stockId
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getStockCandleDailyByStockId(int $stockId)
    {
        return self::where('stock_id', $stockId)
            ->select('stock_id', 'close_price', 'high_price', 'low_price', 'open_price', 'response_status', 'ts', 'volume')
            ->orderBy('ts', 'desc')
            ->get();
    }

    /**
     * Retrieve stock candle daily data by stock symbol and optional date range.
     *
     * @param string $symbol
     * @param string|null $startDate (Y-m-d)
     * @param string|null $endDate (Y-m-d)
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getBySymbolAndDateRange(string $symbol, ?string $startDate = null, ?string $endDate = null)
    {
        // Get the stock ID from stock_symbols table
        $stockId = \DB::table('stock_symbols')
            ->where('symbol', $symbol)
            ->value('id');

        if (!$stockId) {
            return collect(); // Return empty collection if symbol not found
        }

        // Build the query with INNER JOIN to stock_indicators
        $query = self::query()
            ->join('stock_indicators', 'stock_indicators.stock_id', '=', 'stock_candle_daily.stock_id')
            ->where('stock_candle_daily.stock_id', $stockId)
            ->select(
                'stock_candle_daily.stock_id',
                'stock_candle_daily.close_price',
                'stock_candle_daily.high_price',
                'stock_candle_daily.low_price',
                'stock_candle_daily.open_price',
                'stock_candle_daily.response_status',
                'stock_candle_daily.ts',
                'stock_candle_daily.volume'
            )
            ->orderBy('stock_candle_daily.ts', 'desc');

        if ($startDate) {
            $query->where('stock_candle_daily.ts', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('stock_candle_daily.ts', '<=', $endDate);
        }

        return $query->get();
    }
}
