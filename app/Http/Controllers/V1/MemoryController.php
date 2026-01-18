<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Memory;
use App\Models\MemoryComment;
use App\Models\MemoryReaction;
use App\Support\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemoryController extends Controller
{
    /**
     * List family memories
     * GET /v1/memories
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'family_id' => 'nullable|uuid|exists:families,id',
            'experience_id' => 'nullable|uuid|exists:experiences,id',
            'cursor' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $user = $request->user();
        $limit = $request->integer('limit', 20);

        // Get user's family IDs
        $familyIds = $user->families->pluck('id');

        $query = Memory::where(function ($q) use ($user, $familyIds) {
            $q->where('user_id', $user->id)
                ->orWhereIn('family_id', $familyIds);
        })
            ->where(function ($q) use ($user) {
                $q->where('visibility', 'public')
                    ->orWhere('visibility', 'family')
                    ->orWhere('user_id', $user->id);
            })
            ->with(['user', 'experience', 'reactions'])
            ->orderByDesc('created_at');

        if ($request->filled('family_id')) {
            $query->where('family_id', $request->family_id);
        }

        if ($request->filled('experience_id')) {
            $query->where('experience_id', $request->experience_id);
        }

        if ($request->filled('cursor')) {
            $cursor = CursorPaginator::decodeCursor($request->cursor);
            if ($cursor) {
                $query->where('created_at', '<', $cursor['created_at']);
            }
        }

        $memories = $query->limit($limit + 1)->get();
        $hasMore = $memories->count() > $limit;

        if ($hasMore) {
            $memories = $memories->take($limit);
        }

        $nextCursor = null;
        if ($hasMore && $memories->isNotEmpty()) {
            $nextCursor = CursorPaginator::encodeCursor([
                'created_at' => $memories->last()->created_at?->toISOString(),
            ]);
        }

        return response()->json([
            'data' => $memories->map(fn($m) => [
                'id' => $m->id,
                'image_url' => $m->image_url,
                'thumbnail_url' => $m->thumbnail_url,
                'caption' => $m->caption,
                'user' => [
                    'id' => $m->user->id,
                    'name' => $m->user->name,
                    'avatar' => $m->user->avatar,
                ],
                'experience' => $m->experience ? [
                    'id' => $m->experience->id,
                    'title' => $m->experience->title,
                ] : null,
                'reactions' => $m->reactions->groupBy('emoji')->map->count(),
                'taken_at' => $m->taken_at?->toISOString(),
                'created_at' => $m->created_at?->toISOString(),
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
     * Upload memory
     * POST /v1/memories
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image_url' => 'required|url',
            'thumbnail_url' => 'nullable|url',
            'caption' => 'nullable|string|max:500',
            'experience_id' => 'nullable|uuid|exists:experiences,id',
            'plan_id' => 'nullable|uuid|exists:plans,id',
            'family_id' => 'nullable|uuid|exists:families,id',
            'visibility' => 'nullable|in:private,family,collaborators,public',
            'taken_at' => 'nullable|date',
        ]);

        $user = $request->user();

        $memory = Memory::create([
            'user_id' => $user->id,
            'family_id' => $request->family_id ?? $user->getPrimaryFamily()?->id,
            'plan_id' => $request->plan_id,
            'experience_id' => $request->experience_id,
            'image_url' => $request->image_url,
            'thumbnail_url' => $request->thumbnail_url ?? $request->image_url,
            'caption' => $request->caption,
            'visibility' => $request->visibility ?? 'family',
            'taken_at' => $request->taken_at,
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $memory->id,
                'image_url' => $memory->image_url,
                'created_at' => $memory->created_at?->toISOString(),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }

    /**
     * Get memory details
     * GET /v1/memories/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $memory = Memory::with(['user', 'experience', 'reactions.user', 'comments.user'])
            ->findOrFail($id);

        // Check access
        if (!$this->canAccess($memory, $user)) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Access denied.']],
            ], 403);
        }

        return response()->json([
            'data' => [
                'id' => $memory->id,
                'image_url' => $memory->image_url,
                'caption' => $memory->caption,
                'visibility' => $memory->visibility,
                'is_owner' => $memory->user_id === $user->id,
                'user' => [
                    'id' => $memory->user->id,
                    'name' => $memory->user->name,
                    'avatar' => $memory->user->avatar,
                ],
                'experience' => $memory->experience ? [
                    'id' => $memory->experience->id,
                    'title' => $memory->experience->title,
                    'cover_image' => $memory->experience->cover_image,
                ] : null,
                'reactions' => $memory->reactions->map(fn($r) => [
                    'user_id' => $r->user_id,
                    'user_name' => $r->user->name,
                    'emoji' => $r->emoji,
                ]),
                'comments' => $memory->comments->map(fn($c) => [
                    'id' => $c->id,
                    'user_name' => $c->user->name,
                    'user_avatar' => $c->user->avatar,
                    'content' => $c->content,
                    'created_at' => $c->created_at?->toISOString(),
                ]),
                'taken_at' => $memory->taken_at?->toISOString(),
                'created_at' => $memory->created_at?->toISOString(),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Add reaction to memory
     * POST /v1/memories/{id}/reactions
     */
    public function react(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'emoji' => 'required|string|max:10',
        ]);

        $user = $request->user();
        $memory = Memory::findOrFail($id);

        if (!$this->canAccess($memory, $user)) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Access denied.']],
            ], 403);
        }

        MemoryReaction::updateOrCreate(
            ['memory_id' => $id, 'user_id' => $user->id],
            ['emoji' => $request->emoji]
        );

        return response()->json([
            'data' => ['message' => 'Reaction added.'],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Add comment to memory
     * POST /v1/memories/{id}/comments
     */
    public function addComment(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:500',
        ]);

        $user = $request->user();
        $memory = Memory::findOrFail($id);

        if (!$this->canAccess($memory, $user)) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Access denied.']],
            ], 403);
        }

        $comment = MemoryComment::create([
            'memory_id' => $id,
            'user_id' => $user->id,
            'content' => $request->content,
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $comment->id,
                'content' => $comment->content,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }

    /**
     * Delete memory
     * DELETE /v1/memories/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $memory = Memory::findOrFail($id);

        if ($memory->user_id !== $user->id) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Cannot delete this memory.']],
            ], 403);
        }

        $memory->delete();

        return response()->json([
            'data' => ['message' => 'Memory deleted.'],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    protected function canAccess(Memory $memory, $user): bool
    {
        if ($memory->user_id === $user->id) {
            return true;
        }

        if ($memory->visibility === 'public') {
            return true;
        }

        if ($memory->visibility === 'family' && $memory->family_id) {
            return $user->families->contains('id', $memory->family_id);
        }

        return false;
    }
}
