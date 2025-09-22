<?php

namespace App\Http\Controllers\endpoints\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class StockKnowledgeBaseController
 *
 * Handles HTTP requests related to the Trade Engine Knowledge Base.
 *
 * @package App\Http\Controllers\v1
 */
class StockKnowledgeBaseController extends Controller
{
    /**
     * Retrieve all content from the trade_engine_knowledge_base table.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getKnowledgeBaseContent()
    {
        $content = DB::table('trade_engine_knowledge_base')->get();
        return response()->json($content);
    }
}