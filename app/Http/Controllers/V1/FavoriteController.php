<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Jobs\UpdateExperienceSearchJob;
use App\Models\Favorite;
use App\Models\FavoriteList;
use App\Support\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class FavoriteController extends Controller
{
    /**
     * Get user favorites with optional list filter
     * GET /v1/favorites?list_id={id}&cursor={cursor}
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'family_id' => 'nullable|uuid|exists:families,id',
            'list_id' => 'nullable|uuid|exists:favorite_lists,id',
            'cursor' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $user = $request->user();
        $limit = $request->integer('limit', 20);

        // Get lists
        $lists = FavoriteList::where('user_id', $user->id)
            ->orWhere('family_id', $user->getPrimaryFamily()?->id)
            ->withCount('favorites')
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'emoji' => $l->emoji,
                'count' => $l->favorites_count,
                'is_default' => $l->is_default,
            ]);

        // Get favorites
        $query = Favorite::where('user_id', $user->id)
            ->with(['experience.category'])
            ->orderByDesc('saved_at');

        if ($request->filled('list_id')) {
            $query->where('list_id', $request->list_id);
        }

        if ($request->filled('family_id')) {
            $query->where('family_id', $request->family_id);
        }

        // Cursor pagination
        if ($request->filled('cursor')) {
            $cursor = CursorPaginator::decodeCursor($request->cursor);
            if ($cursor) {
                $query->where('saved_at', '<', $cursor['saved_at']);
            }
        }

        $favorites = $query->limit($limit + 1)->get();
        $hasMore = $favorites->count() > $limit;

        if ($hasMore) {
            $favorites = $favorites->take($limit);
        }

        $nextCursor = null;
        if ($hasMore && $favorites->isNotEmpty()) {
            $lastItem = $favorites->last();
            $nextCursor = CursorPaginator::encodeCursor([
                'saved_at' => $lastItem->saved_at?->toISOString(),
            ]);
        }

        return response()->json([
            'data' => [
                'lists' => $lists,
                'experiences' => $favorites->map(fn($f) => [
                    'id' => $f->experience_id,
                    'title' => $f->experience?->title,
                    'cover_image' => $f->experience?->cover_image,
                    'category' => $f->experience?->category ? [
                        'name' => $f->experience->category->name,
                        'emoji' => $f->experience->category->emoji,
                    ] : null,
                    'saved_at' => $f->saved_at?->toISOString(),
                    'saved_by' => $f->scope,
                ]),
            ],
            'meta' => [
                'success' => true,
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
            ],
            'errors' => null,
        ]);
    }

    /**
     * Save experience to favorites
     * POST /v1/favorites
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'experience_id' => 'required|uuid|exists:experiences,id',
            'list_id' => 'nullable|uuid|exists:favorite_lists,id',
            'family_id' => 'nullable|uuid|exists:families,id',
            'scope' => 'nullable|in:user,family',
        ]);

        $user = $request->user();

        // Check if already saved
        $existing = Favorite::where('user_id', $user->id)
            ->where('experience_id', $request->experience_id)
            ->first();

        if ($existing) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Experience already saved.']],
            ], 409);
        }

        $favorite = Favorite::create([
            'user_id' => $user->id,
            'experience_id' => $request->experience_id,
            'family_id' => $request->family_id ?? $user->getPrimaryFamily()?->id,
            'list_id' => $request->list_id,
            'scope' => $request->scope ?? 'user',
            'saved_at' => now(),
        ]);

        // Increment Redis counter (will be flushed to DB by job)
        Redis::hincrby("experience:metrics:{$request->experience_id}", 'saves', 1);

        // Mark experience for read model update
        Redis::sadd('experience_search:dirty', $request->experience_id);

        // Invalidate caches
        Cache::tags(['experience:' . $request->experience_id])->flush();

        return response()->json([
            'data' => [
                'id' => $favorite->id,
                'experience_id' => $favorite->experience_id,
                'saved_at' => $favorite->saved_at?->toISOString(),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }

    /**
     * Remove experience from favorites
     * DELETE /v1/favorites/{experience_id}
     */
    public function destroy(Request $request, string $experienceId): JsonResponse
    {
        $user = $request->user();

        $favorite = Favorite::where('user_id', $user->id)
            ->where('experience_id', $experienceId)
            ->first();

        if (!$favorite) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Favorite not found.']],
            ], 404);
        }

        $favorite->delete();

        // Increment unsave counter
        Redis::hincrby("experience:metrics:{$experienceId}", 'unsaves', 1);

        // Mark for read model update
        Redis::sadd('experience_search:dirty', $experienceId);

        // Invalidate caches
        Cache::tags(['experience:' . $experienceId])->flush();

        return response()->json([
            'data' => ['message' => 'Removed from favorites.'],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }
}
