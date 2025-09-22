<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockInstitutionalOwnership extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_institutional_ownership';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'change',
        'cik',
        'name',
        'noVoting',
        'percentage',
        'putCall',
        'share',
        'sharedVoting',
        'soleVoting',
        'value',
        'report_date',
        'from',
        'to'
    ];

    /**
     * Insert or update stock institutional ownership at stock_institutional_ownership table.
     *
     * @param int $stockId
     * @param array $data
     * @return bool
     */
    public static function updateStockInstitutionalOwnership(int $stockId, array $data): bool
    {
        
        return (bool) self::updateOrCreate(
            [
                'stock_id' => $stockId,
                'cik' => $data['cik'],
                'from' => $data['from'],
                'to' => $data['to']
            ], // search criteria
            [
                'change' => $data['change'],
                'name' => $data['name'],
                'noVoting' => $data['noVoting'],
                'percentage' => $data['percentage'],
                'putCall' => $data['putCall'],
                'share' => $data['share'],
                'sharedVoting' => $data['sharedVoting'],
                'soleVoting' => $data['soleVoting'],
                'value' => $data['value'],
                'report_date'=> $data['report_date']
            ]
        );
    }

    /**
     * Get institutional ownership data for a specific stock ID.
     *
     * @param int $stockId
     * @return array
     */
    public static function getInstitutionalOwnershipByStockId(int $stockId): array
    {
        return self::where('stock_id', $stockId)
            ->join('stock_symbols', 'stock_institutional_ownership.stock_id', '=', 'stock_symbols.id')
            ->get(['stock_institutional_ownership.*', 'stock_symbols.symbol'])
            ->toArray();
    }

    /**
     * Retrieve upgrade/downgrade records with pagination.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAllInstitutionalOwnership (int $limit = 50, int $offset = 0) {
        return self::join('stock_symbols', 'stock_institutional_ownership.stock_id', '=', 'stock_symbols.id')
            ->limit($limit)
            ->offset($offset)
            ->get(['stock_institutional_ownership.*', 'stock_symbols.symbol'])
            ->toArray();
    }
}