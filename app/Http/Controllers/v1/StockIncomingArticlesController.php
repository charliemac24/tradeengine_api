<?php

namespace App\Http\Controllers\v1;

use App\Models\v1\StockIncomingArticles;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class StockIncomingArticlesController extends Controller
{
    /**
     * Capture and save an incoming article.
     *
     * Endpoint: POST http://api.trendseekermax.com/v1/send_article
     *
     * Expected request payload (JSON):
     * {
     *   "title": "Article Title",
     *   "description": "Article Description",
     *   "featured_image": "http://example.com/image.jpg",
     *   "date_created": "2025-04-14" // Or in any accepted date format.
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendArticle(Request $request)
    {
        // Validate the incoming request data.
        $validatedData = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'required|string',
        ]);

        // Create a new record using the validated data.
        $article = StockIncomingArticles::create($validatedData);

        // Return a success response.
        return response()->json([
            'message' => 'Article created successfully.',
            'data'    => $article,
        ], 201);
    }
}
