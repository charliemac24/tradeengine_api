<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockUpgradeDowngrade extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_upgrade_downgrade';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'grade_time',
        'company',
        'from_grade',
        'to_grade',
        'action'
    ];

    /**
     * Insert or update stock upgrade downgrade at stock_upgrade_downgrade table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockUpgradeDowngrade(int $stockId, array $data): bool
    {
        return (bool) self::updateOrCreate(
            [
                'stock_id' => $stockId,
                'grade_time' => date('Y-m-d H:i:s',$data['gradeTime'])
            ], // search criteria
            [
                'company' => $data['company'],
                'from_grade' => $data['fromGrade'],
                'to_grade' => $data['toGrade'],
                'action' => $data['action']
            ]
        );
    }

    /**
     * Retrieve upgrade/downgrade records with pagination.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAllUpgradeDowngrades(int $limit = 50, int $offset = 0) {
        return self::join('stock_symbols', 'stock_upgrade_downgrade.stock_id', '=', 'stock_symbols.id')
            ->orderBy('grade_time', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get(['stock_upgrade_downgrade.*', 'stock_symbols.symbol'])
            ->toArray();
    }

    /**
     * Get upgrade/downgrade records for a specific stock.
     *
     * @param int $stockId
     * @return array
     */
    public static function getUpgradeDowngradeByStockId(int $stockId)
    {
        return self::where('stock_id', $stockId)
            ->orderBy('grade_time', 'desc')
            ->get();
    }
}