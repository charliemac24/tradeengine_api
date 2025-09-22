<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockCompanyPeers extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_company_peers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock_id',
        'peer_symbol',
    ];

    /**
     * Update or create a record in the stock_company_peers table.
     *
     * @param int $stockId
     * @param string $peerSymbol
     * @return bool
     */
    public static function updateOrCreatePeer(int $stockId, string $peerSymbol): bool
    {
        return (bool) self::firstOrCreate(
            [
                'stock_id' => $stockId,
                'peer_symbol' => $peerSymbol,
            ]
        );
    }
}