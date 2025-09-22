<?php

namespace App\Http\Controllers\user;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class UserSocialPost extends Controller
{
    /**
     * Store a new social post with an attachment.
     */
    public function storeWithAttachment(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'user_post_type' => 'string|max:50',
            'user_post_content' => 'required|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5MB max
        ]);

        // Create the post
        $postId = DB::table('social_posts')->insertGetId([
            'user_id' => $request->input('user_id'),
            'user_post_type' => $request->input('user_post_type') ?? 'text',
            'user_post_content' => $request->input('user_post_content'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Handle attachment if present
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('social_attachments', 'public');
            DB::table('social_post_attachments')->insert([
                'social_post_id' => $postId,
                'file_url' => '/storage/' . $path,
                'file_type' => $file->getClientMimeType(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Social post created successfully.', 'id' => $postId]);
    }

    /**
     * Get all social posts by a user.
     */
    public function getByUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $posts = DB::table('social_posts')
            ->where('user_id', $request->input('user_id'))
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['posts' => $posts]);
    }

    /**
     * Delete a social post.
     */
    public function delete(Request $request)
    {
        $request->validate([
            'post_id' => 'required|integer|exists:social_posts,id',
        ]);

        DB::table('social_posts')
            ->where('id', $request->input('post_id'))
            ->delete();

        return response()->json(['message' => 'Social post deleted successfully.']);
    }
    
    /**
     * Update a social post.
     */
    public function update(Request $request)
    {
        $request->validate([
            'post_id' => 'required|integer|exists:social_posts,id',
            'user_post_content' => 'required|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5MB max
            'remove_attachment' => 'nullable|boolean',
        ]);

        // Update the post content
        DB::table('social_posts')
            ->where('id', $request->input('post_id'))
            ->update([
                'user_post_content' => $request->input('user_post_content'),
                'updated_at' => now(),
            ]);

        // Handle attachment removal
        if ($request->boolean('remove_attachment')) {
            // Delete the attachment record and optionally the file
            $attachment = DB::table('social_post_attachments')
                ->where('social_post_id', $request->input('post_id'))
                ->first();

            if ($attachment) {
                // Optionally delete the file from storage
                $filePath = str_replace('/storage/', '', $attachment->file_url);
                \Storage::disk('public')->delete($filePath);

                DB::table('social_post_attachments')
                    ->where('social_post_id', $request->input('post_id'))
                    ->delete();
            }
        }

        // Handle attachment update (replace or add new)
        if ($request->hasFile('attachment')) {
            // Remove old attachment if exists
            $oldAttachment = DB::table('social_post_attachments')
                ->where('social_post_id', $request->input('post_id'))
                ->first();

            if ($oldAttachment) {
                $oldFilePath = str_replace('/storage/', '', $oldAttachment->file_url);
                \Storage::disk('public')->delete($oldFilePath);

                DB::table('social_post_attachments')
                    ->where('social_post_id', $request->input('post_id'))
                    ->delete();
            }

            // Store new attachment
            $file = $request->file('attachment');
            $path = $file->store('social_attachments', 'public');
            DB::table('social_post_attachments')->insert([
                'social_post_id' => $request->input('post_id'),
                'file_url' => '/storage/' . $path,
                'file_type' => $file->getClientMimeType(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Social post updated successfully.']);
    }   

    /**
     * Get all social posts, ordered by most recent first.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllPosts(Request $request)
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
            'sort' => 'sometimes|string|in:newest,oldest',
        ]);

        $limit = (int) $request->query('limit', 20);
        $offset = (int) $request->query('offset', 0);
        $sort = $request->query('sort', 'newest');

        $query = DB::table('social_posts')
            ->join('users', 'social_posts.user_id', '=', 'users.id')
            ->select(
                'social_posts.*',
                'users.name as user_name',
                'users.email as user_email' // add more user fields if needed
            );

        if ($sort === 'oldest') {
            $query->orderBy('social_posts.created_at', 'asc');
        } else {
            // newest (default)
            $query->orderByDesc('social_posts.created_at');
        }

        $total = (clone $query)->count();

        $posts = $query->offset($offset)->limit($limit)->get();

        return response()->json([
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'sort' => $sort,
            ],
            'posts' => $posts,
        ]);
    }

    /**
     * Search social posts by content.
     *
     * Query params:
     * - q (required) search query string
     * - limit (optional) default 20
     * - offset (optional) default 0
     * - sort (optional) newest|oldest
     *
     * Returns JSON: { meta: { total, limit, offset, sort }, posts: [...] }
     */
    public function searchPosts(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1|max:500',
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
            'sort' => 'sometimes|string|in:newest,oldest',
        ]);

        $q = $request->input('q');
        $limit = (int) $request->query('limit', 20);
        $offset = (int) $request->query('offset', 0);
        $sort = $request->query('sort', 'newest');

        // Basic wildcard search on user_post_content. Use bindings to avoid injection.
        $wild = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

        $baseQuery = DB::table('social_posts')
            ->join('users', 'social_posts.user_id', '=', 'users.id')
            ->select('social_posts.*', 'users.name as user_name')
            ->where('social_posts.user_post_content', 'like', DB::raw("?"))
            ->setBindings([$wild], 'where');

        // Count total matching rows
        $total = (clone $baseQuery)->count();

        // Apply sorting
        if ($sort === 'oldest') {
            $baseQuery->orderBy('social_posts.created_at', 'asc');
        } else {
            $baseQuery->orderByDesc('social_posts.created_at');
        }

        $posts = $baseQuery->offset($offset)->limit($limit)->get();

        return response()->json([
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'sort' => $sort,
            ],
            'posts' => $posts,
        ]);
    }

    /**
     * Allow a user to post a comment on a social post.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function comment(Request $request)
    {
        $request->validate([
            'social_post_id' => 'required|integer|exists:social_posts,id',
            'user_id' => 'required|integer|exists:users,id',
            'comment' => 'required|string',
        ]);

        $id = DB::table('social_post_comments')->insertGetId([
            'social_post_id' => $request->input('social_post_id'),
            'user_id' => $request->input('user_id'),
            'comment' => $request->input('comment'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Comment posted successfully.',
            'id' => $id
        ]);
    }

    /**
     * Allow a user to follow another user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function follow(Request $request)
    {
        $request->validate([
            'follower_id' => 'required|integer|exists:users,id',
            'following_id' => 'required|integer|exists:users,id|different:follower_id',
        ]);

        // Prevent duplicate follows
        $exists = DB::table('social_followers')
            ->where('follower_id', $request->input('follower_id'))
            ->where('following_id', $request->input('following_id'))
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already following this user.'], 409);
        }

        DB::table('social_followers')->insert([
            'follower_id' => $request->input('follower_id'),
            'following_id' => $request->input('following_id'),
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Successfully followed the user.']);
    }

    /**
     * Allow a user to unfollow another user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unfollow(Request $request)
    {
        $request->validate([
            'follower_id' => 'required|integer|exists:users,id',
            'following_id' => 'required|integer|exists:users,id|different:follower_id',
        ]);

        $deleted = DB::table('social_followers')
            ->where('follower_id', $request->input('follower_id'))
            ->where('following_id', $request->input('following_id'))
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Successfully unfollowed the user.']);
        } else {
            return response()->json(['message' => 'You are not following this user.'], 404);
        }
    }

    /**
     * Allow a user to share a social post.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function share(Request $request)
    {
        $request->validate([
            'social_post_id' => 'required|integer|exists:social_posts,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        // Prevent duplicate shares by the same user for the same post (optional)
        $exists = DB::table('social_post_shares')
            ->where('social_post_id', $request->input('social_post_id'))
            ->where('user_id', $request->input('user_id'))
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You have already shared this post.'], 409);
        }

        DB::table('social_post_shares')->insert([
            'social_post_id' => $request->input('social_post_id'),
            'user_id' => $request->input('user_id'),
            'shared_at' => now(),
        ]);

        return response()->json(['message' => 'Post shared successfully.']);
    }

    /**
     * Allow a user to unshare (remove their share of) a social post.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unshare(Request $request)
    {
        $request->validate([
            'social_post_id' => 'required|integer|exists:social_posts,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $deleted = DB::table('social_post_shares')
            ->where('social_post_id', $request->input('social_post_id'))
            ->where('user_id', $request->input('user_id'))
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Post unshared successfully.']);
        } else {
            return response()->json(['message' => 'You have not shared this post.'], 404);
        }
    }

    /**
     * Get all comments for a specific social post.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getComments(Request $request)
    {
        $request->validate([
            'social_post_id' => 'required|integer|exists:social_posts,id',
        ]);

        $comments = DB::table('social_post_comments')
            ->join('users', 'social_post_comments.user_id', '=', 'users.id')
            ->select(
                'social_post_comments.*',
                'users.name as user_name',
                'users.email as user_email' // add more user fields if needed
            )
            ->where('social_post_comments.social_post_id', $request->input('social_post_id'))
            ->orderBy('social_post_comments.created_at', 'asc')
            ->get();

        return response()->json(['comments' => $comments]);
    }

    /**
     * Save a view when a user views a social post.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewPost(Request $request)
    {
        $request->validate([
            'post_id' => 'required|integer|exists:social_posts,id',
            'viewer_id' => 'required|integer|exists:users,id',
        ]);

        // Prevent duplicate view for the same user and post in a short period (optional)
        $alreadyViewed = DB::table('social_post_views')
            ->where('post_id', $request->input('post_id'))
            ->where('viewer_id', $request->input('viewer_id'))
            ->whereDate('created_at', now()->toDateString())
            ->exists();

        if (!$alreadyViewed) {
            DB::table('social_post_views')->insert([
                'post_id' => $request->input('post_id'),
                'viewer_id' => $request->input('viewer_id'),
                'created_at' => now(),
            ]);
        }

        return response()->json(['message' => 'View recorded.']);
    }

    /**
     * Delete a comment from a social post.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteComment(Request $request)
    {
        $request->validate([
            'comment_id' => 'required|integer|exists:social_post_comments,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        // Optional: Only allow the comment owner to delete their comment
        $deleted = DB::table('social_post_comments')
            ->where('id', $request->input('comment_id'))
            ->where('user_id', $request->input('user_id'))
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Comment deleted successfully.']);
        } else {
            return response()->json(['message' => 'Comment not found or not authorized.'], 404);
        }
    }

    /**
     * Allow a user to love (like) a social post.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function lovePost(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'post_id' => 'required|integer|exists:social_posts,id',
        ]);

        // Prevent duplicate love by the same user for the same post
        $exists = DB::table('social_post_love')
            ->where('user_id', $request->input('user_id'))
            ->where('post_id', $request->input('post_id'))
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You have already loved this post.'], 409);
        }

        DB::table('social_post_love')->insert([
            'user_id' => $request->input('user_id'),
            'post_id' => $request->input('post_id'),
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Post loved successfully.']);
    }

    /**
     * Allow a user to remove their love (like) from a social post.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unlovePost(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'post_id' => 'required|integer|exists:social_posts,id',
        ]);

        $deleted = DB::table('social_post_love')
            ->where('user_id', $request->input('user_id'))
            ->where('post_id', $request->input('post_id'))
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Love removed from post successfully.']);
        } else {
            return response()->json(['message' => 'You have not loved this post.'], 404);
        }
    }

    /**
     * Get all followers of a user, including follower names.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFollowers(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $followers = DB::table('social_followers')
            ->join('users', 'social_followers.follower_id', '=', 'users.id')
            ->where('social_followers.following_id', $request->input('user_id'))
            ->select(
                'users.id as follower_id',
                'users.name as follower_name'
            )
            ->get();

        return response()->json(['followers' => $followers]);
    }

    /**
     * Get all users that a given user is following.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFollowing(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $following = DB::table('social_followers')
            ->join('users', 'social_followers.following_id', '=', 'users.id')
            ->where('social_followers.follower_id', $request->input('user_id'))
            ->select(
                'following_id',
                'users.name as following_name'
            )
            ->get();

        return response()->json(['following' => $following]);
    }

    /**
     * Get all posts that a user has liked.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLikedPosts(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
        ]);

        $limit = (int) $request->query('limit', 50);
        $offset = (int) $request->query('offset', 0);

        $likedPosts = DB::table('social_post_love')
            ->join('social_posts', 'social_post_love.post_id', '=', 'social_posts.id')
            ->join('users as authors', 'social_posts.user_id', '=', 'authors.id')
            ->where('social_post_love.user_id', $request->input('user_id'))
            ->select(
                'social_posts.*',
                'authors.id as author_id',
                'authors.name as author_name',
                'social_post_love.id as love_id',
                'social_post_love.created_at as liked_at'
            )
            ->orderByDesc('social_post_love.created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json(['liked_posts' => $likedPosts]);
    }

    /**
     * Get all followers of a user, including follower names.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLikers(Request $request)
    {
        $request->validate([
            'post_id' => 'required|integer|exists:social_posts,id',
        ]);

        $likers = DB::table('social_post_love')
            ->join('users', 'social_post_love.user_id', '=', 'users.id')
            ->where('social_post_love.post_id', $request->input('post_id'))
            ->select(
                'users.id as liker_id',
                'users.name as liker_name'
            )
            ->get();

        return response()->json(['likers' => $likers]);
    }

    /**
     * Get all followers of a user, including follower names.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSharers(Request $request)
    {
        $request->validate([
            'post_id' => 'required|integer|exists:social_posts,id',
        ]);

        $sharers = DB::table('social_post_shares')
            ->join('users', 'social_post_shares.user_id', '=', 'users.id')
            ->where('social_post_shares.social_post_id', $request->input('post_id'))
            ->select(
                'users.id as sharer_id',
                'users.name as sharer_name'
            )
            ->get();

        return response()->json(['sharers' => $sharers]);
    }
}