<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockEarningsCalendar extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_earnings_calendar';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'cal_date',
        'eps_actual',
        'eps_estimate',
        'hour',
        'quarter',
        'revenue_actual',
        'revenue_estimate',
        'symbol',
        'cal_year'
    ];

    /**
     * Insert or update stock earnings calendar at stock_earnings_calendar table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockEarningsCalendar(int $stockId, array $data): bool
    {       

        $toNumericOrNull = function ($v) {
        // treat '', null, '-', 'N/A' as null
        if ($v === null) return null;
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '' || $v === '-' || strcasecmp($v, 'N/A') === 0) return null;
            // remove thousand separators if any
            $v = str_replace([',', ' '], '', $v);
        }
        // accept only numeric
            if (!is_numeric($v)) return null;
            return (string)$v;  // send as string to avoid PHP/PDO int issues
        };

        return (bool) self::updateOrCreate(
            [
                'stock_id' => $stockId,
                'cal_date' => $data['date']
            ],
            [
                'eps_actual' => $data['epsActual'],
                'eps_estimate' => $data['epsEstimate'],
                'hour' => $data['hour'],
                'quarter' => $data['quarter'],
                'revenue_actual' => $toNumericOrNull($data['revenueActual']),
                'revenue_estimate' => $toNumericOrNull($data['revenueEstimate']),
                'symbol' => $data['symbol'],
                'cal_year' => $data['year']
            ]
        );
    }

    /**
     * Get earnings calendar data by stock ID
     *
     * @param int $stockId
     * @return array
     */
    public static function getEarningsCalendarByStockId($stockId)
    {
        return self::where('stock_id', $stockId)
                    ->orderBy('cal_date', 'desc')
                    ->get()
                    ->toArray();
    }

    /**
     * Get all earnings calendar data with market cap limit
     * 
     * @return array
     */
    public static function getAllEarningsCalendarMarketCapLimit()
    {
        
        return self::join('stock_symbol_info', 'stock_earnings_calendar.stock_id', '=', 'stock_symbol_info.stock_id')
                ->join('stock_symbols', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')
                ->select(
                    'stock_earnings_calendar.*',
                    'stock_symbol_info.market_cap',
                    'stock_symbol_info.company_name'
                )
                ->where('stock_earnings_calendar.cal_date', '<=', now()->addDays(7)->toDateString())
                ->whereIn('stock_symbol_info.currency', ['USD', 'CAD'])
                ->where('stock_symbol_info.market_cap', '>=', 200000000000)
                ->where('stock_symbols.priority', '=', 1)
                ->orderBy('stock_earnings_calendar.cal_date', 'desc')
                ->orderBy('stock_symbol_info.market_cap', 'desc')
                ->get()
                ->toArray();
        
    }

    /**
     * Get all earnings calendar data
     *
     * @return array
     */
    public static function getAllEarningsCalendar($daysBack)
    {    
        $dback = now()->subDays($daysBack);
        $_7days = now()->addDays(7);

        $symbol = $_GET['symbol'] ?? '';

        $query = self::join('stock_symbol_info', 'stock_earnings_calendar.stock_id', '=', 'stock_symbol_info.stock_id')
            ->join('stock_symbols', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')
            ->select([
                'stock_earnings_calendar.*',
                'stock_symbol_info.market_cap',
                'stock_symbol_info.company_name',
            ])            
            ->whereIn('stock_symbol_info.currency', ['USD', 'CAD'])
            ->where('stock_earnings_calendar.hide', '=', 0)
            ->where('stock_symbols.priority', '=', 1);
                
        // If days_back is provided, filter by updated_at
        if ($daysBack && is_numeric($daysBack)) {            
            $query->where('stock_earnings_calendar.updated_at', '>=', now()->subDays($daysBack));
        }
        if ( !$dback) {
            $query->whereDate('stock_earnings_calendar.cal_date', '<=', $_7days);
        }
        if ( $symbol) {
            $query->where('stock_symbols.symbol', '=', $symbol);
        }

        return $query
            ->orderBy('stock_earnings_calendar.cal_date', 'desc')
            ->orderBy('stock_symbol_info.market_cap', 'desc')
            ->limit(2000)
            ->offset(0)
            ->get()
            ->toArray();
    }
}