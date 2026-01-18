<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Experience;
use App\Models\Review;
use App\Models\ReviewPhoto;
use App\Support\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ReviewController extends Controller
{
    /**
     * List reviews for experience
     * GET /v1/experiences/{experienceId}/reviews
     */
    public function index(Request $request, string $experienceId): JsonResponse
    {
        $request->validate([
            'sort' => 'nullable|in:recent,helpful,rating_high,rating_low',
            'cursor' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $limit = $request->integer('limit', 20);
        $sort = $request->get('sort', 'helpful');

        $query = Review::where('experience_id', $experienceId)
            ->public()
            ->with(['user', 'photos']);

        // Sorting
        $query = match ($sort) {
            'recent' => $query->orderByDesc('created_at'),
            'helpful' => $query->orderByDesc('helpful_count'),
            'rating_high' => $query->orderByDesc('rating'),
            'rating_low' => $query->orderBy('rating'),
            default => $query->orderByDesc('helpful_count'),
        };

        // Cursor pagination
        if ($request->filled('cursor')) {
            $cursor = CursorPaginator::decodeCursor($request->cursor);
            if ($cursor) {
                $query->where('id', '<', $cursor['id']);
            }
        }

        $reviews = $query->limit($limit + 1)->get();
        $hasMore = $reviews->count() > $limit;

        if ($hasMore) {
            $reviews = $reviews->take($limit);
        }

        $nextCursor = null;
        if ($hasMore && $reviews->isNotEmpty()) {
            $nextCursor = CursorPaginator::encodeCursor([
                'id' => $reviews->last()->id,
            ]);
        }

        // Check which reviews current user marked as helpful
        $user = $request->user();
        $helpfulIds = $user->reviewHelpful ?? collect();

        return response()->json([
            'data' => $reviews->map(fn($r) => [
                'id' => $r->id,
                'user' => [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'avatar' => $r->user->avatar,
                ],
                'rating' => $r->rating,
                'comment' => $r->comment,
                'tags' => $r->tags ?? [],
                'photos' => $r->photos->pluck('url'),
                'helpful_count' => $r->helpful_count,
                'visited_at' => $r->visited_at?->format('Y-m-d'),
                'created_at' => $r->created_at?->toISOString(),
            ]),
            'meta' => [
                'success' => true,
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
            ],
            'errors' => null,
        ]);
    }

    /**
     * Create review
     * POST /v1/experiences/{experienceId}/reviews
     */
    public function store(Request $request, string $experienceId): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'visibility' => 'nullable|in:private,public',
            'visited_at' => 'nullable|date|before_or_equal:today',
            'photo_urls' => 'nullable|array|max:5',
            'photo_urls.*' => 'url',
        ]);

        $user = $request->user();

        // Check if user already reviewed this experience
        $existing = Review::where('experience_id', $experienceId)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'You have already reviewed this experience.']],
            ], 409);
        }

        $review = DB::transaction(function () use ($request, $user, $experienceId) {
            $review = Review::create([
                'experience_id' => $experienceId,
                'user_id' => $user->id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'tags' => $request->tags,
                'visibility' => $request->visibility ?? 'public',
                'visited_at' => $request->visited_at,
                'helpful_count' => 0,
            ]);

            // Add photos
            if ($request->filled('photo_urls')) {
                foreach ($request->photo_urls as $index => $url) {
                    ReviewPhoto::create([
                        'review_id' => $review->id,
                        'url' => $url,
                        'order' => $index,
                    ]);
                }
            }

            return $review;
        });

        // Update experience counters via Redis
        Redis::hincrby("experience:metrics:{$experienceId}", 'reviews', 1);
        Redis::sadd('experience_search:dirty', $experienceId);

        // Recalculate average rating
        $this->updateAverageRating($experienceId);

        // Invalidate caches
        Cache::tags(['experience:' . $experienceId])->flush();

        return response()->json([
            'data' => [
                'id' => $review->id,
                'rating' => $review->rating,
                'created_at' => $review->created_at?->toISOString(),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }

    /**
     * Mark review as helpful
     * POST /v1/reviews/{id}/helpful
     */
    public function markHelpful(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $review = Review::findOrFail($id);

        // Check if already marked
        $existing = DB::table('review_helpful')
            ->where('review_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Already marked as helpful.']],
            ], 409);
        }

        DB::table('review_helpful')->insert([
            'review_id' => $id,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $review->increment('helpful_count');

        return response()->json([
            'data' => ['helpful_count' => $review->helpful_count],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Update review
     * PUT /v1/reviews/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $user = $request->user();
        $review = Review::findOrFail($id);

        if ($review->user_id !== $user->id) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Cannot edit this review.']],
            ], 403);
        }

        $review->update($request->only(['rating', 'comment', 'tags']));

        // Recalculate average if rating changed
        if ($request->has('rating')) {
            $this->updateAverageRating($review->experience_id);
            Cache::tags(['experience:' . $review->experience_id])->flush();
        }

        return response()->json([
            'data' => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'updated_at' => $review->updated_at?->toISOString(),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Delete review (soft delete)
     * DELETE /v1/reviews/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $review = Review::findOrFail($id);

        if ($review->user_id !== $user->id) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Cannot delete this review.']],
            ], 403);
        }

        $experienceId = $review->experience_id;
        $review->delete();

        // Update counters
        $this->updateAverageRating($experienceId);
        Redis::sadd('experience_search:dirty', $experienceId);
        Cache::tags(['experience:' . $experienceId])->flush();

        return response()->json([
            'data' => ['message' => 'Review deleted.'],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    protected function updateAverageRating(string $experienceId): void
    {
        $stats = Review::where('experience_id', $experienceId)
            ->public()
            ->selectRaw('COUNT(*) as count, AVG(rating) as average')
            ->first();

        Experience::where('id', $experienceId)->update([
            'reviews_count' => $stats->count ?? 0,
            'average_rating' => $stats->average ? round($stats->average, 1) : null,
        ]);
    }
}
