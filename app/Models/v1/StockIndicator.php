<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockIndicator extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_indicators';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'ema_50',
        'ema_100',
        'ema_200',
        'ema_10',
        'sma_10',
        'sma_20',
        'sma_50',
        'sma_200',
        'macd',
        'macd_hist',
        'macd_signal_line',
        'rsi',
        'aroon_up',
        'aroon_down',
        'cci',
        'lower_b',
        'price',
        'adx',
        'upperband',
        'middleband',
        'slowk',
        'slowd',
        'sar',
        'obv',
        'bullish',
        'bearish',
        'sma_100',
        'plus_di',
        'minus_di'
    ];

    /**
     * Update or create the ema_50 field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateEma50(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['ema_50' => $value]
        );
    }

    /**
     * Update or create the ema_100 field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateEma100(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['ema_100' => $value]
        );
    }

    /**
     * Update or create the ema_200 field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateEma200(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['ema_200' => $value]
        );
    }

    /**
     * Update or create the ema_10 field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateEma10(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['ema_10' => $value]
        );
    }
    
    /**
     * Update or create the sma_10 field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateSma10(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['sma_10' => $value]
        );
    }
    
    /**
     * Update or create the sma_20 field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateSma20(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['sma_20' => $value]
        );
    }


    /**
     * Update or create the sma_50 field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateSma50(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['sma_50' => $value]
        );
    }

    /**
     * Update or create the sma_200 field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateSma200(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['sma_200' => $value]
        );
    }

    /**
     * Update or create the sma_100 field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateSma100(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['sma_100' => $value]
        );
    }

    /**
     * Update or create the macd field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateMacd(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['macd' => $value]
        );
    }

    /**
     * Update or create the macd_signal_line field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateMacdSignalLine(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['macd_signal_line' => $value]
        );
    }

    /**
     * Update or create the macd_hist field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateMacdHist(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['macd_hist' => $value]
        );
    }

    /**
     * Update or create the rsi field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateRsi(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['rsi' => $value]
        );
    }

    /**
     * Update or create the aroon_up field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateAroonUp(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['aroon_up' => $value]
        );
    }

    /**
     * Update or create the aroon_down field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateAroonDown(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['aroon_down' => $value]
        );
    }

    /**
     * Update or create the cci field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateCci(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['cci' => $value]
        );
    }

    /**
     * Update or create the lower_b field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateLowerb(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['lower_b' => $value]
        );
    }

    /**
     * Update or create the price field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updatePrice(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['price' => $value]
        );
    }

    /**
     * Update or create the adx field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateAdx(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['adx' => $value]
        );
    }

    /**
     * Update or create the upperband field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateUpperband(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['upperband' => $value]
        );
    }

    public static function updateMiddleband(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['middleband' => $value]
        );
    }

    /**
     * Update or create the slowk field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateSlowk(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['slowk' => $value]
        );
    }

    /**
     * Update or create the slowd field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateSlowd(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['slowd' => $value]
        );
    }

    /**
     * Update or create the sar field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateSar(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['sar' => $value]
        );
    }

    /**
     * Update or create the obv field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateObv(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['obv' => $value]
        );
    }

    /**
     * Update or create the bullish field for a given stock_id.
     *
     * @param int $stockId
     * @param int $value
     * @return bool
     */
    public static function updateBullish(int $stockId, int $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['bullish' => $value]
        );
    }

    /**
     * Update or create the bearish field for a given stock_id.
     *
     * @param int $stockId
     * @param int $value
     * @return bool
     */
    public static function updateBearish(int $stockId, int $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['bearish' => $value]
        );
    }
    
     /**
     * Remove duplicate records based on stock_id, retaining the record with the lowest id.
     *
     * @return void
     */
    public static function removeDuplicateStockIds()
    {
        $duplicates = self::select('stock_id', \DB::raw('MIN(id) as min_id'))
            ->groupBy('stock_id')
            ->havingRaw('COUNT(stock_id) > 1')
            ->pluck('min_id');
            
       

       self::whereNotIn('id', $duplicates)
            ->whereIn('stock_id', function ($query) {
                $query->select('stock_id')
                    ->from('stock_indicators')
                    ->groupBy('stock_id')
                    ->havingRaw('COUNT(stock_id) > 1');
            })
            ->delete();
    }
    
    /**
     * Update or create the plus_id field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updatePlusdi(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['plus_di' => $value]
        );
    }
    
    /**
     * Update or create the minus_id field for a given stock_id.
     *
     * @param int $stockId
     * @param float $value
     * @return bool
     */
    public static function updateMinusdi(int $stockId, float $value): bool
    {
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockId],
            ['minus_di' => $value]
        );
    }
}