<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockInfo extends Model
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
        'stock_id',
        'description',
        'address',
        'city',
        'sector',
        'industry',
        'website',
        'company_name',
        'currency',
        'stocks_logo',
        'market_cap',
        'share_outstanding'
    ];    

    /**
     * Insert or update stock info at stock_symbol_info table.
     *
     * @param array $stockSymbolProfile
     * @return bool
     */
    public static function updateStockSymbolProfile(array $stockSymbolProfile): bool
    {       

        
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockSymbolProfile['stock_id']], // search criteria
            [
                'description' => $stockSymbolProfile['description'],
                'address' => $stockSymbolProfile['address'],
                'city' => $stockSymbolProfile['city'],
                'sector' => $stockSymbolProfile['sector'],
                'industry' => $stockSymbolProfile['industry'],
                'website' => $stockSymbolProfile['website'],
                'company_name' => $stockSymbolProfile['name'],
                'currency' => $stockSymbolProfile['currency'],
                'stocks_logo' => $stockSymbolProfile['stocks_logo'],
                'market_cap' => strpos((string)$stockSymbolProfile['market_cap'], '.') === false ? $stockSymbolProfile['market_cap'] . '.00' : $stockSymbolProfile['market_cap'],
                'share_outstanding' => $stockSymbolProfile['share_outstanding']
            ]
        );
    }

    public static function updateStockSymbolName(array $stockSymbolProfile): bool
    {       
        return (bool) self::updateOrCreate(
            ['stock_id' => $stockSymbolProfile['stock_id']], // search criteria
            [
                'company_name' => $stockSymbolProfile['name']
            ]
        );
    }

    public static function getStocksWithoutCompanyName(): array
    {
        return self::join('stock_symbols', 'stock_symbol_info.stock_id', '=', 'stock_symbols.id')
            ->whereNull('stock_symbol_info.company_name')
            ->orWhere('stock_symbol_info.company_name', '=', '')
            ->pluck('stock_symbols.symbol')
            ->toArray();
    }
    
}
