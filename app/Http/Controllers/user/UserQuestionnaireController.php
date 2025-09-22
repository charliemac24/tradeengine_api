<?php

namespace App\Http\Controllers\user;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class UserQuestionnaireController extends Controller
{

    /**
     * Add user choice to the database.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addUserChoice(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'portfolio_id' => 'integer|exists:user_portfolios,id',
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|integer|exists:investment_profile_questionnaires,id',
            'answers.*.choice_id' => 'required|integer|exists:investment_q_choices,id',
        ]);

        $userId = $request->input('user_id');
        $portfolioId = $request->input('portfolio_id');

        $insertData = [];
        $choiceIds = [];
        foreach ($request->input('answers') as $answer) {
            $insertData[] = [
                'user_id' => $userId,
                'question_id' => $answer['question_id'],
                'choice_id' => $answer['choice_id'],
                'portfolio_id' => $portfolioId ? $portfolioId : null,
                'created_at' => now(),
            ];
            $choiceIds[] = $answer['choice_id'];
        }

        DB::table('investment_profile_choices')->insert($insertData);

        // Calculate total score for the selected choice_ids
        $totalScore = DB::table('investment_q_choices')
            ->whereIn('id', $choiceIds)
            ->sum('score');

        // Get the tag id based on the score
        $tagId = DB::table('investment_profile_tags')
            ->where('score_min', '<=', $totalScore)
            ->where('score_max', '>=', $totalScore)
            ->value('id');

        // Check if the user already has a record in investment_p_tag
        $hasProfile = DB::table('investment_p_tag')
            ->where('user_id', $request->input('user_id'))
            ->exists();

        $default = $hasProfile ? 0 : 1;

        // Insert into investment_p_tag table
        DB::table('investment_p_tag')->insert([
            'user_id' => $userId,
            'portfolio_id' => $portfolioId ? $portfolioId : null,
            'profile_tag' => $tagId,
            'profile_score' => $totalScore,
            'default_profile' => $default,
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Choices saved successfully',
            'total_score' => $totalScore,
            'profile_tag_id' => $tagId
        ]);
    }

    /**
     * Insert a new record into the investment_p_tag table.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addInvestmentPTag(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'portfolio_id' => 'integer',
            'profile_tag' => 'required|integer|exists:investment_profile_tags,id',
        ]);

        
        $hasProfile = DB::table('investment_p_tag')
                        ->where('user_id', $request->input('user_id'))
                        ->exists();

        $default = $hasProfile ? 0 : 1;

        // Calculate total score for the selected choice_ids
        $profileTagId = $request->input('profile_tag');
        $score_max = DB::table('investment_profile_tags')
            ->where('id', $profileTagId)
            ->value('score_max');

        $id = DB::table('investment_p_tag')->insertGetId([
            'user_id' => $request->input('user_id'),
            'portfolio_id' => $request->input('portfolio_id'),
            'profile_tag' => $request->input('profile_tag'),
            'profile_score' => $score_max,
            'default_profile' => $default,
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Investment profile tag added successfully.',
            'profile_score' => $score_max
        ]);
    }

    /**
     * Update an existing record in the investment_p_tag table based on its ID.
     * If 'answers' are provided, recalculate the score and tag as in addUserChoice.
     * If not, update using the logic from addInvestmentPTag.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateInvestmentPTag(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:investment_p_tag,id',
            'answers' => 'array',
            'answers.*.question_id' => 'required_with:answers|integer|exists:investment_profile_questionnaires,id',
            'answers.*.choice_id' => 'required_with:answers|integer|exists:investment_q_choices,id',
            'profile_tag' => 'integer|exists:investment_profile_tags,id',
        ]);

        $data = [];
        // If answers are provided, recalculate score and tag
        if ($request->has('answers') && is_array($request->input('answers')) && count($request->input('answers')) > 0) {
            $choiceIds = [];
            foreach ($request->input('answers') as $answer) {
                $choiceIds[] = $answer['choice_id'];
            }
            // Calculate total score for the selected choice_ids
            $totalScore = DB::table('investment_q_choices')
                ->whereIn('id', $choiceIds)
                ->sum('score');

            // Get the tag id based on the score
            $tagId = DB::table('investment_profile_tags')
                ->where('score_min', '<=', $totalScore)
                ->where('score_max', '>=', $totalScore)
                ->value('id');

            $data['profile_tag'] = $tagId;
            $data['profile_score'] = $totalScore;
        } elseif ($request->has('profile_tag')) {
            // If skipping questionnaire, use profile_tag and its score_max
            $profileTagId = $request->input('profile_tag');
            $score_max = DB::table('investment_profile_tags')
                ->where('id', $profileTagId)
                ->value('score_max');
            $data['profile_tag'] = $profileTagId;
            $data['profile_score'] = $score_max;
        }

        $data['updated_at'] = now();

        $updated = DB::table('investment_p_tag')
            ->where('id', $request->input('id'))
            ->update($data);

        if ($updated) {
            return response()->json(['message' => 'Investment profile tag updated successfully.']);
        } else {
            return response()->json(['message' => 'No changes made or record not found.'], 404);
        }
    }

    /**
     * Get the label for the score range.
     *
     * @param int $totalScore
     * @return string
     */
    private function getScoreLabel($totalScore)
    {
        if ($totalScore >= 5 && $totalScore <= 7) {
            return "Capital Preservation";
        } elseif ($totalScore >= 8 && $totalScore <= 10) {
            return "Conservative Income";
        } elseif ($totalScore >= 11 && $totalScore <= 13) {
            return "Balanced Growth";
        } elseif ($totalScore >= 14 && $totalScore <= 16) {
            return "Growth-Oriented";
        } elseif ($totalScore >= 17 && $totalScore <= 18) {
            return "Aggressive Growth";
        } elseif ($totalScore >= 19 && $totalScore <= 20) {
            return "Speculative Growth";
        } else {
            return "No Label";
        }
    }

    /**
     * Get questionnaire questions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getQuestions()
    {
        $questions = DB::table('investment_profile_questionnaires')
            ->select('id as question_id', 'question as question_text')
            ->get();

        return response()->json($questions);
    }

    /**
     * Get questionnaire choices.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChoices()
    {
        $choices = DB::table('investment_q_choices')
            ->select('id as choice_id', 'question_id', 'choice as choice_text', 'score')
            ->get();

        return response()->json($choices);
    }

    /**
     * Get profile tags.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfileTags()
    {
        $tags = DB::table('investment_profile_tags')->get();
        return response()->json($tags);
    }

    /**
     * Get user suggested tag.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserSuggestedTag(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'portfolio_id' => 'nullable',
        ]);

        $query = DB::table('investment_p_tag')
        ->join('investment_profile_tags', 'investment_p_tag.profile_tag', '=', 'investment_profile_tags.id')
        ->select(
            'investment_p_tag.id as p_tag_id',
            'investment_p_tag.user_id',
            'investment_p_tag.portfolio_id',
            'investment_profile_tags.suggested_tag',
            'investment_profile_tags.tag_description'
        )
        ->where('investment_p_tag.user_id', $request->input('user_id'));

        if ($request->filled('portfolio_id')) {
            $query->where('investment_p_tag.portfolio_id', $request->input('portfolio_id'));
        } else {
            $query->whereNull('investment_p_tag.portfolio_id');
        }

        $result = $query->orderByDesc('investment_p_tag.id')->first();

        if (!$result) {
            return response()->json(['message' => 'No suggested tag found for this user and portfolio.'], 404);
        }

        return response()->json($result);
    }

    /**
     * Get all investment profile tags created by the specified user,
     * joined with investment_profile_tags and user_portfolios (if portfolio_id is not null).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserInvestmentPTags(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $query = DB::table('investment_p_tag')
            ->select(
                'investment_p_tag.id',
                'investment_p_tag.user_id',
                'investment_p_tag.portfolio_id',
                'investment_p_tag.profile_tag AS profile_tag_id',
                'investment_p_tag.profile_score',
                'investment_p_tag.default_profile',
                'investment_profile_tags.suggested_tag',
                'investment_profile_tags.tag_description',
                'user_portfolios.portfolio AS portfolio_name',
            )
            ->join('investment_profile_tags', 'investment_profile_tags.id', '=', 'investment_p_tag.profile_tag')
            ->leftJoin('user_portfolios', function($join) {
                $join->on('user_portfolios.id', '=', 'investment_p_tag.portfolio_id');
            })
            ->where('investment_p_tag.user_id', $request->input('user_id'));

        $tags = $query->get();

        return response()->json($tags);
    }
}