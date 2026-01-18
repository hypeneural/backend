<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\FavoriteList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteListController extends Controller
{
    /**
     * Create favorite list
     * POST /v1/favorite-lists
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'emoji' => 'nullable|string|max:10',
        ]);

        $user = $request->user();

        $list = FavoriteList::create([
            'user_id' => $user->id,
            'family_id' => $user->getPrimaryFamily()?->id,
            'name' => $request->name,
            'emoji' => $request->emoji ?? '📁',
            'is_default' => false,
        ]);

        return response()->json([
            'data' => [
                'id' => $list->id,
                'name' => $list->name,
                'emoji' => $list->emoji,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }

    /**
     * Update favorite list
     * PUT /v1/favorite-lists/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:100',
            'emoji' => 'nullable|string|max:10',
        ]);

        $user = $request->user();

        $list = FavoriteList::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $list->update($request->only(['name', 'emoji']));

        return response()->json([
            'data' => [
                'id' => $list->id,
                'name' => $list->name,
                'emoji' => $list->emoji,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Delete favorite list
     * DELETE /v1/favorite-lists/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $list = FavoriteList::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($list->is_default) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Cannot delete default list.']],
            ], 400);
        }

        // Move favorites to null list before deleting
        $list->favorites()->update(['list_id' => null]);
        $list->delete();

        return response()->json([
            'data' => ['message' => 'List deleted.'],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }
}
