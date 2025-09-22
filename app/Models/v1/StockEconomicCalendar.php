<?php
/**
 * Class StockEconomicCalendar
 *
 * Handles operations related to the economic calendar for stocks,
 * such as retrieving, filtering, and managing economic events data.
 *
 * @package App\Models
 */

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockEconomicCalendar extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_economic_calendar';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'actual',
        'country',
        'estimate',
        'event',
        'impact',
        'prev',
        'econ_time',
        'unit'
    ];

    /**
     * Insert or update stock Economic calendar at stock_economic_calendar table.
     *
     * @param array $data
     * @return bool
     */
    public static function updateStockEconomicCalendar(array $data): bool
    {       
        return (bool) self::updateOrCreate(
            [
                'event' => $data['event'],
                'econ_time' => $data['time'],
                'country' => $data['country']
            ],
            [
                'actual' => $data['actual'],
                'country' => $data['country'],
                'estimate' => $data['estimate'],
                'event' => $data['event'],
                'impact' => $data['impact'],
                'prev' => $data['prev'],
                'econ_time' => $data['time'],
                'unit' => $data['unit']
            ]
        );
    }

    /**
     * Retrieve economic calendar events for a specific stock ID.
     *
     * @param int $stockId The ID of the stock to filter economic calendar events.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getEconomicCalendarByStockId($stockId)
    {
        return static::where('stock_id', $stockId)->get();
    }

    /**
     * Retrieve economic calendar events for the next 1 day.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getEconomicCalendar1day()
    {
        return static::whereBetween('event_date', [now(), now()->addDay()])->get();
    }

    /**
     * Retrieve economic calendar events for the next 3 days.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getEconomicCalendar3days()
    {
        return static::whereBetween('event_date', [now(), now()->addDays(3)])->get();
    }

    /**
     * Retrieve the first 100 records from stock_economic_calendar table with pagination.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAllEconomicCalendar(int $limit = 150, int $offset = 0) {
        return self::orderBy('econ_time', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
    }
}
