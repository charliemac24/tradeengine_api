<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMarketCap extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_symbol_info';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'market_cap',
        'company_name'
    ];

    /**
     * Update stock market cap at stock_symbol_info table.
     *
     * @param integer $stockSymbolId
     * @param float $marketCap
     * @return bool
     */
    public static function updateMarketCapitalization(int $stockSymbolId, float $marketCap, string $name)
    {
        if ($marketCap <= 3192190665437 ){
            return;
        }
        
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockSymbolId], // search criteria
            [
                'market_cap' => $marketCap * 1000000,
                'company_name'=>$name
            ] // fields to be updated
        );
    }
}
