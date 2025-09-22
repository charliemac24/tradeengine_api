<?php

namespace App\Models\v1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;

class Stock extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_symbols';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'symbol',
        'priority'
    ];

    /**
     * Insert a new record into the stock_symbols table.
     *
     * @param string $symbol
     * @return bool
     */
    public static function insertIntoStockSymbols(string $symbol): bool
    {
        $priority = in_array($symbol, self::top300StockSymbolUS()) ? 1 : 0;

        return (bool) self::updateOrCreate(
            ['symbol' => $symbol],
            ['priority' => $priority]
        );
    }

    /**
     * Get the top 300 stock symbols in US
     * 
     * @return array An array of stock symbols
     */
    public static function top300StockSymbolUS()
    {
        return [
            "AAPL", "MSFT", "AMZN", "GOOGL", "GOOG", "FB", "TSLA", "BRK.B", "NVDA", "JPM",
            "JNJ", "UNH", "V", "PG", "HD", "MA", "DIS", "PYPL", "BAC", "INTC",
            "NFLX", "CMCSA", "PFE", "KO", "PEP", "T", "MRK", "WMT", "CSCO", "XOM",
            "VZ", "ADBE", "CRM", "ABT", "NKE", "TMO", "LLY", "MCD", "DHR", "MDT",
            "HON", "UNP", "LIN", "WFC", "BMY", "NEE", "COST", "PM", "AVGO", "ACN",
            "TXN", "QCOM", "ORCL", "IBM", "AMGN", "CVX", "SBUX", "MMM", "GE", "CHTR",
            "INTU", "CAT", "LMT", "BA", "SPGI", "GS", "DE", "ISRG", "BLK", "AXP",
            "SYK", "MO", "MDLZ", "BKNG", "AMT", "ADI", "LRCX", "GILD", "FIS", "NOW",
            "PLD", "USB", "TGT", "CB", "CI", "ZTS", "C", "LOW", "SCHW", "MMC",
            "DUK", "SO", "BDX", "PNC", "APD", "EL", "TJX", "TFC", "CL", "ADP",
            "ITW", "WM", "D", "NSC", "ETN", "HUM", "EMR", "NOC", "FDX", "EW",
            "ROP", "PSA", "KMB", "TEL", "SHW", "FCX", "AON", "HCA", "MCO", "SPG",
            "F", "PSX", "MAR", "BAX", "AEP", "TRV", "PRU", "ECL", "SLB", "AIG",
            "GM", "SRE", "MET", "MS", "KR", "GIS", "ADM", "MPC", "AFL", "ORLY",
            "CTSH", "DLR", "SBAC", "HLT", "ALL", "STZ", "CMI", "VLO", "XLNX", "DOW",
            "EXC", "REGN", "IDXX", "VTRS", "WBA", "PAYX", "TT", "IQV", "NEM", "O",
            "AZO", "ROST", "PH", "KHC", "EA", "VRTX", "CTAS", "PEG", "APTV", "EBAY",
            "PPG", "SYY", "DFS", "WELL", "ED", "DAL", "MSI", "AMP", "YUM", "AME",
            "A", "HIG", "PCAR", "TDG", "ODFL", "WST", "SNPS", "EFX", "CDNS", "RMD",
            "MTD", "ANET", "WTW", "HSY", "FTNT", "MNST", "FAST", "KEYS", "LYB", "BKR",
            "FTV", "RSG", "TSCO", "DHI", "ALGN", "LHX", "LDOS", "KMI", "FANG", "MKC",
            "GWW", "CHD", "MLM", "RCL", "NTRS", "HPE", "DG", "MTB", "AVY", "TTWO",
            "COO", "SWKS", "EXPE", "HBAN", "CF", "LKQ", "JKHY", "BXP", "WRB", "NVR",
            "CMS", "HOLX", "CNP", "BIO", "LUV", "IT", "ESS", "ARE", "FDS", "CLX",
            "TSN", "IP", "VMC", "TRGP", "ETR", "CPRT", "XEL", "BF.B", "CTRA", "CFG",
            "IRM", "IPG", "MOS", "SIVB", "DOV", "NUE", "SYF", "CDW", "PKI", "AES",
            "PWR", "ATO", "NWL", "RJF", "DRI", "HES", "CAG", "LEN", "APA", "ZBRA",
            "CCL", "HII", "UDR", "KEY", "AAP", "HPQ", "PXD", "CHRW", "TXT", "HAS",
            "OXY", "GPC", "CTXS", "CME", "NLSN", "WDC", "DRE", "WEC", "K", "ALB",
            "CERN", "ZBH", "DVA", "BWA", "STX", "HST", "NTAP", "CE", "EQR", "COG",
            "AEE", "RE", "BEN", "EVRG", "REG", "FRC", "QRVO", "SLG", "LNT", "LH",
            "AAL", "AKAM", "J", "EXPD", "AOS", "UDR", "KIM", "CUBE", "RHI", "SEE",
        ];
        
    }

    /**
     * Get stock symbols with their information, sorted by priority.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getStockSymbolsWithInfo()
    {
        /**return DB::table('stock_symbols')
            ->join('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')
            ->select('stock_symbols.*', 'stock_symbol_info.*')
            ->orderBy('stock_symbol_info.market_cap', 'desc')
            ->limit(1300)
            ->get();**/
            
            /**return DB::table('stock_symbols')
                    ->join('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')
                    ->select('stock_symbols.*', 'stock_symbol_info.*')
                    ->where('stock_symbols.priority', '=', 1)
                    ->orderBy('stock_symbol_info.market_cap', 'desc')
                    ->limit(2000)
                    ->get();**/

                    return DB::table('stock_symbols')
                    ->join('stock_symbol_info', 'stock_symbols.id', '=', 'stock_symbol_info.stock_id')
                    ->join('stocks_by_market_cap', 'stocks_by_market_cap.symbol', '=', 'stock_symbols.symbol') // Inner join with stocks_by_market_cap
                    ->select('stock_symbols.*', 'stock_symbol_info.*', 'stocks_by_market_cap.*') // Include columns from stocks_by_market_cap
                    ->where('stocks_by_market_cap.processed', '=', 1)
                    ->where('stocks_by_market_cap.notpriority', '=', 0)
                    ->orderBy('stock_symbol_info.market_cap', 'desc')
                    ->limit(2000)
                    ->get();
    }    

    public static function getAllStockSymbols()
    {
        return DB::table('stock_symbols')->orderBy('priority','desc')->pluck('symbol')->toArray();
    }

    public static function stockSymbols()
    {
        // Retrieve the data and return only the stock symbols in an array
        return self::getStockSymbolsWithInfo()->pluck('symbol')->toArray();
    }
    
    /**
     * Update the priority to 1 for a given stock symbol.
     *
     * @param string $symbol The stock symbol to update.
     * @return bool
     */
    public static function updatePriorityBySymbol(string $symbol): bool
    {
        return self::where('symbol', $symbol)->update(['priority' => 1]) > 0;
    }

    public static function getStocksForIndicators()
    {
        return DB::table('stock_symbols')
            ->join('stocks_by_market_cap', 'stock_symbols.symbol', '=', 'stocks_by_market_cap.symbol')
            ->where('stocks_by_market_cap.processed_indicator', '=', 0)
            ->where('stocks_by_market_cap.notpriority', '=', 0)
            ->pluck('stock_symbols.symbol')
            ->toArray();
    }

    public static function getStocksForIndicatorsNonHist()
    {
        return DB::table('stock_symbols')
            ->join('stocks_by_market_cap', 'stock_symbols.symbol', '=', 'stocks_by_market_cap.symbol')
            ->where('stocks_by_market_cap.processed_indicator_non_hist', '=', 0)
            ->where('stocks_by_market_cap.notpriority', '=', 0)
            ->pluck('stock_symbols.symbol')
            ->toArray();
    }

    public static function setProcessedIndicatorBySymbol(string $symbol): bool
    {
        // Get stock_id from stock_symbols table
        $stockId = DB::table('stock_symbols')->where('symbol', $symbol)->value('id');

        return DB::table('stocks_by_market_cap')
            ->where('symbol', $symbol)
            ->where('notpriority', '=', 0)
            ->update(['processed_indicator' => 1]) > 0;
    }

    public static function setProcessedIndicatorNonHistBySymbol(string $symbol): bool
    {
        // Get stock_id from stock_symbols table
        $stockId = DB::table('stock_symbols')->where('symbol', $symbol)->value('id');

        return DB::table('stocks_by_market_cap')
            ->where('symbol', $symbol)
            ->where('notpriority', '=', 0)
            ->update(['processed_indicator_non_hist' => 1]) > 0;
    }

    public static function unsetAllProcessedIndicatorBySymbol(): bool
    {
        return DB::table('stocks_by_market_cap')
            ->where('notpriority', '=', 0)
            ->update(['processed_indicator' => 0]) > 0;
    }

    public static function unsetAllProcessedIndicatorNonHistBySymbol(): bool
    {
        return DB::table('stocks_by_market_cap')
            ->where('notpriority', '=', 0)
            ->update(['processed_indicator_non_hist' => 0]) > 0;
    }

    public static function countUnprocessedIndicatorStocks(): int
    {
        return DB::table('stocks_by_market_cap')
            ->where('processed_indicator', 0)
            ->where('notpriority', '=', 0)
            ->count();
    }

    public static function countUnprocessedIndicatorNonHistStocks(): int
    {
        return DB::table('stocks_by_market_cap')
            ->where('processed_indicator_non_hist', 0)
            ->where('notpriority', '=', 0)
            ->count();
    }

    public static function isProcessedIndicatorBySymbol(string $symbol): bool
    {
        return DB::table('stocks_by_market_cap')
            ->where('symbol', $symbol)
            ->where('notpriority', '=', 0)
            ->where('processed_indicator', 1)
            ->exists();
    }

    public static function isProcessedIndicatorNonHistBySymbol(string $symbol): bool
    {
        return DB::table('stocks_by_market_cap')
            ->where('symbol', $symbol)
            ->where('notpriority', '=', 0)
            ->where('processed_indicator_non_hist', 1)
            ->exists();
    }
}
