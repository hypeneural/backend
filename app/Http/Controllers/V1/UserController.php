<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Update current user profile
     * PUT /v1/users/me
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
        ]);

        $user = $request->user();

        $user->update($request->only(['name', 'email']));

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'updated_at' => $user->updated_at?->toISOString(),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Update user avatar
     * PATCH /v1/users/me/avatar
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar_url' => 'required|url',
        ]);

        $user = $request->user();
        $user->update(['avatar' => $request->avatar_url]);

        return response()->json([
            'data' => [
                'avatar' => $user->avatar,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Update user location
     * POST /v1/users/me/location
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'city_id' => 'nullable|uuid|exists:cities,id',
        ]);

        $user = $request->user();

        $user->update([
            'last_lat' => $request->lat,
            'last_lng' => $request->lng,
            'last_location_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'lat' => (float) $user->last_lat,
                'lng' => (float) $user->last_lng,
                'updated_at' => $user->last_location_at?->toISOString(),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Get user statistics
     * GET /v1/users/me/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['stats', 'badges', 'favorites', 'reviews', 'plans', 'memories']);

        $stats = $user->stats;
        $currentXp = $stats?->xp ?? 0;
        $level = $stats?->level ?? 1;
        $nextLevelXp = $this->calculateNextLevelXp($level);
        $levelProgress = $nextLevelXp > 0 ? ($currentXp % 200) / 200 : 0;

        return response()->json([
            'data' => [
                'xp' => $currentXp,
                'level' => $level,
                'level_progress' => round($levelProgress, 2),
                'next_level_xp' => $nextLevelXp,
                'streak_days' => $stats?->streak_days ?? 0,
                'longest_streak' => $stats?->longest_streak ?? 0,
                'total_saves' => $user->favorites->count(),
                'total_reviews' => $user->reviews->count(),
                'total_plans' => $user->plans->count(),
                'total_memories' => $user->memories->count(),
                'total_referrals' => $stats?->total_referrals ?? 0,
                'badges' => $user->badges->map(fn($b) => [
                    'slug' => $b->slug,
                    'name' => $b->name,
                    'earned_at' => $b->pivot->earned_at?->toISOString(),
                ]),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Soft delete user account
     * DELETE /v1/users/me
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'confirmation' => 'required|in:DELETE',
        ]);

        $user = $request->user();
        $user->delete();

        return response()->json([
            'data' => [
                'message' => 'Account deactivated successfully.',
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    protected function calculateNextLevelXp(int $level): int
    {
        return ($level + 1) * 200;
    }
}
