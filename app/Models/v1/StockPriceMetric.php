<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockPriceMetric extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_price_metrics';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'data_100_day_ema',
        'data_100_day_sma',
        'data_10_day_average_trading_volume',
        'data_10_day_ema',
        'data_10_day_sma',
        'data_14_day_rsi',
        'data_1_month_high',
        'data_1_month_high_date',
        'data_50_day_ema',
        'data_50_day_sma',
        'data_52_week_high',
        'data_52_week_high_date',
        'data_52_week_low',
        'data_52_week_low_date',
        'data_5_day_ema',
        'data_ytd_price_return'
    ];

    /**
     * Insert or update stock price metrics at stock_price_metrics table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockPriceMetric(int $stockId, array $data): bool
    {       
        $metric = $data['data'];

        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId], // search criteria
            [
                'data_100_day_ema' => $metric['100DayEMA'],
                'data_100_day_sma' => $metric['100DaySMA'],
                'data_10_day_average_trading_volume' => $metric['10DayAverageTradingVolume'],
                'data_10_day_ema' => $metric['10DayEMA'],
                'data_10_day_sma' => $metric['10DaySMA'],
                'data_14_day_rsi' => $metric['14DayRSI'],
                'data_1_month_high' => $metric['1MonthHigh'],
                'data_1_month_high_date' => $metric['1MonthHighDate'],
                'data_50_day_ema' => $metric['50DayEMA'],
                'data_50_day_sma' => $metric['50DaySMA'],
                'data_52_week_high' => $metric['52WeekHigh'],
                'data_52_week_high_date' => $metric['52WeekHighDate'],
                'data_52_week_low' => $metric['52WeekLow'],
                'data_52_week_low_date' => $metric['52WeekLowDate'],
                'data_5_day_ema' => $metric['5DayEMA'],
                'data_ytd_price_return' => $metric['ytdPriceReturn']
            ]
        );
    }

}
