<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\FamilyInvite;
use App\Models\FamilyInviteUse;
use App\Models\FamilyUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FamilyController extends Controller
{
    /**
     * Get current family
     * GET /v1/family?family_id={id}
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get specific family or primary family
        $familyId = $request->query('family_id');

        if ($familyId) {
            $family = Family::findOrFail($familyId);

            // Check if user belongs to this family
            if (!$family->hasUser($user)) {
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => [['message' => 'You are not a member of this family.']],
                ], 403);
            }
        } else {
            $family = $user->getPrimaryFamily();

            if (!$family) {
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => [['message' => 'No family found.']],
                ], 404);
            }
        }

        $family->load(['users', 'dependents', 'preferences', 'preferenceCategories.category']);

        return response()->json([
            'data' => [
                'id' => $family->id,
                'name' => $family->name,
                'type' => $family->type,
                'avatar' => $family->avatar,
                'vibe_preset' => $family->vibe_preset,
                'members' => $family->users->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'avatar' => $u->avatar,
                    'role' => $u->pivot->role,
                    'is_current_user' => $u->id === $user->id,
                ]),
                'dependents' => $family->dependents->map(fn($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'avatar' => $d->avatar,
                    'age_group' => $d->age_group,
                ]),
                'preferences' => $family->preferences ? [
                    'max_distance_km' => $family->preferences->max_distance_km,
                    'default_price' => $family->preferences->default_price,
                    'avoid' => $family->preferences->avoid ?? [],
                    'favorite_categories' => $family->preferenceCategories->map(fn($pc) => [
                        'id' => $pc->category_id,
                        'name' => $pc->category?->name,
                        'weight' => $pc->weight,
                    ]),
                ] : null,
                'stats' => [
                    'experiences_visited' => $family->plans()->where('status', 'completed')->count(),
                    'memories_count' => $family->memories()->count(),
                    'favorites_count' => $family->favorites()->count(),
                ],
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Generate invite code
     * POST /v1/family/invite
     */
    public function invite(Request $request): JsonResponse
    {
        $request->validate([
            'family_id' => 'nullable|uuid|exists:families,id',
            'max_uses' => 'nullable|integer|min:1|max:100',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
        ]);

        $user = $request->user();
        $familyId = $request->family_id ?? $user->getPrimaryFamily()?->id;

        if (!$familyId) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'No family specified.']],
            ], 400);
        }

        // Check if user is admin of this family
        $familyUser = FamilyUser::where('family_id', $familyId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$familyUser || !$familyUser->isAdmin()) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'You must be an admin to invite members.']],
            ], 403);
        }

        $code = FamilyInvite::generateCode();
        $expiresAt = now()->addDays($request->integer('expires_in_days', 7));

        $invite = FamilyInvite::create([
            'family_id' => $familyId,
            'type' => 'family',
            'code' => $code,
            'token_hash' => hash('sha256', $code),
            'max_uses' => $request->integer('max_uses', 1),
            'uses_count' => 0,
            'expires_at' => $expiresAt,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'data' => [
                'code' => $invite->code,
                'expires_at' => $invite->expires_at->toISOString(),
                'max_uses' => $invite->max_uses,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }

    /**
     * Create a new family
     * POST /v1/family
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'nullable|in:family,friends,couple',
        ]);

        $user = $request->user();

        $family = DB::transaction(function () use ($request, $user) {
            $family = Family::create([
                'name' => $request->name,
                'type' => $request->type ?? 'family',
            ]);

            FamilyUser::create([
                'family_id' => $family->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return $family;
        });

        return response()->json([
            'data' => [
                'id' => $family->id,
                'name' => $family->name,
                'type' => $family->type,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }

    /**
     * Update family
     * PUT /v1/family
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'family_id' => 'nullable|uuid|exists:families,id',
            'name' => 'nullable|string|max:100',
            'type' => 'nullable|in:family,friends,couple',
            'avatar' => 'nullable|url',
            'vibe_preset' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $familyId = $request->family_id ?? $user->getPrimaryFamily()?->id;

        if (!$familyId) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'No family specified.']],
            ], 400);
        }

        $familyUser = FamilyUser::where('family_id', $familyId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$familyUser || !$familyUser->isAdmin()) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'You must be an admin to update the family.']],
            ], 403);
        }

        $family = Family::findOrFail($familyId);
        $family->update($request->only(['name', 'type', 'avatar', 'vibe_preset']));

        return response()->json([
            'data' => [
                'id' => $family->id,
                'name' => $family->name,
                'type' => $family->type,
                'avatar' => $family->avatar,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Join family with invite code
     * POST /v1/family/join
     */
    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:20',
        ]);

        $user = $request->user();
        $code = strtoupper(trim($request->code));

        $invite = FamilyInvite::where('code', $code)->first();

        if (!$invite) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Invalid invite code.']],
            ], 404);
        }

        if (!$invite->isValid()) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'This invite has expired or reached maximum uses.']],
            ], 400);
        }

        // Check if already a member
        $existingMember = FamilyUser::where('family_id', $invite->family_id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingMember && $existingMember->status === 'active') {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'You are already a member of this family.']],
            ], 400);
        }

        // Join family
        DB::transaction(function () use ($user, $invite, $request) {
            FamilyUser::updateOrCreate(
                [
                    'family_id' => $invite->family_id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => 'member',
                    'status' => 'active',
                    'joined_at' => now(),
                    'invited_by' => $invite->created_by,
                ]
            );

            $invite->increment('uses_count');

            FamilyInviteUse::create([
                'invite_id' => $invite->id,
                'user_id' => $user->id,
                'used_at' => now(),
                'ip_hash' => hash('sha256', $request->ip()),
                'user_agent' => $request->userAgent(),
            ]);
        });

        $family = Family::find($invite->family_id);

        return response()->json([
            'data' => [
                'family_id' => $family->id,
                'family_name' => $family->name,
                'message' => 'Successfully joined ' . $family->name,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Leave a family
     * POST /v1/family/leave
     */
    public function leave(Request $request): JsonResponse
    {
        $request->validate([
            'family_id' => 'nullable|uuid|exists:families,id',
        ]);

        $user = $request->user();
        $familyId = $request->family_id ?? $user->getPrimaryFamily()?->id;

        if (!$familyId) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'No family specified.']],
            ], 400);
        }

        $familyUser = FamilyUser::where('family_id', $familyId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$familyUser) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'You are not a member of this family.']],
            ], 400);
        }

        if ($familyUser->isOwner()) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Owner cannot leave the family. Transfer ownership first.']],
            ], 400);
        }

        $familyUser->update([
            'status' => 'left',
            'left_at' => now(),
        ]);

        return response()->json([
            'data' => ['message' => 'You have left the family.'],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Remove a member from family (admin only)
     * DELETE /v1/family/{familyId}/members/{userId}
     */
    public function removeMember(Request $request, string $familyId, string $userId): JsonResponse
    {
        $user = $request->user();

        $familyUser = FamilyUser::where('family_id', $familyId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$familyUser || !$familyUser->isAdmin()) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'You must be an admin to remove members.']],
            ], 403);
        }

        $memberToRemove = FamilyUser::where('family_id', $familyId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$memberToRemove) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Member not found.']],
            ], 404);
        }

        if ($memberToRemove->isOwner()) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Cannot remove the owner.']],
            ], 400);
        }

        $memberToRemove->update([
            'status' => 'removed',
            'left_at' => now(),
        ]);

        return response()->json([
            'data' => ['message' => 'Member removed from family.'],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }
}
