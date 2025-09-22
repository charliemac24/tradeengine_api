<?php



namespace App\Http\Controllers\endpoints\v1;



use App\Http\Controllers\Controller;

use App\Models\v1\Stock;

use App\Models\v1\StockCompanyNews;

use Illuminate\Http\Request;



class StockCompanyNewsController extends Controller

{

    /**

     * Get company news for a specific stock symbol.

     *

     * @param string $symbol

     * @return \Illuminate\Http\JsonResponse

     */

    public function getCompanyNewsBySymbol(string $symbol)

    {

        // Convert symbol to uppercase

        $symbol = strtoupper($symbol);
        $from_date = $_GET['from_date'] ?? null;

        // Get the stock ID

        $stockId = Stock::where('symbol', $symbol)->value('id');

        

        if (!$stockId) {

            return response()->json([

                'error' => 'Stock not found',

                'message' => "No stock found with symbol: {$symbol}"

            ], 404);

        }

        

        // Get the company news

        $news = StockCompanyNews::where('stock_id', $stockId)
            // only apply the date filter if the user passed from_date
                ->when($from_date, function ($q, $from_date) {
                    $q->where('stock_company_news.date_time', '>=', $from_date);
                })
            ->orderBy('date_time', 'desc')

            ->get();

        

        if ($news->isEmpty()) {

            return response()->json([

                'error' => 'News not found',

                'message' => "No company news available for stock: {$symbol}"

            ], 404);

        }

        

        return response()->json([

            'success' => true,

            'data' => $news

        ]);

    }



    /**

     * Get all company news with pagination.

     *

     * @param Request $request

     * @return \Illuminate\Http\JsonResponse

     */

    public function getAllCompanyNews(Request $request)

    {

        $from_date = $request->query('from_date', null);

        // Validate and sanitize input

        $limit = min(max((int)$request->query('limit', 50), 1), 100); // Between 1 and 100

        $offset = max((int)$request->query('offset', 0), 0); // Non-negative

        

        $news = StockCompanyNews::join('stock_symbols', 'stock_company_news.stock_id', '=', 'stock_symbols.id')

        // only apply the date filter if the user passed from_date
        ->when($from_date, function ($q, $from_date) {
            $q->where('stock_company_news.date_time', '>=', $from_date);
        })

            ->orderBy('stock_company_news.date_time', 'desc')

            ->limit($limit)

            ->offset($offset)

            ->get(['stock_company_news.*', 'stock_symbols.symbol']);

        

        return response()->json([

            'success' => true,

            'data' => $news,

            'pagination' => [

                'limit' => $limit,

                'offset' => $offset,

                'count' => count($news)

            ]

        ]);

    }

} 