<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanCollaborator;
use App\Models\PlanExperience;
use App\Support\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    /**
     * List user plans
     * GET /v1/plans
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:draft,planned,in_progress,completed',
            'cursor' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $user = $request->user();
        $limit = $request->integer('limit', 20);

        $query = Plan::where('user_id', $user->id)
            ->orWhereHas('collaborators', fn($q) => $q->where('user_id', $user->id))
            ->with(['experiences' => fn($q) => $q->limit(3)])
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('cursor')) {
            $cursor = CursorPaginator::decodeCursor($request->cursor);
            if ($cursor) {
                $query->where('updated_at', '<', $cursor['updated_at']);
            }
        }

        $plans = $query->limit($limit + 1)->get();
        $hasMore = $plans->count() > $limit;

        if ($hasMore) {
            $plans = $plans->take($limit);
        }

        $nextCursor = null;
        if ($hasMore && $plans->isNotEmpty()) {
            $nextCursor = CursorPaginator::encodeCursor([
                'updated_at' => $plans->last()->updated_at?->toISOString(),
            ]);
        }

        return response()->json([
            'data' => $plans->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'date' => $p->date?->format('Y-m-d'),
                'status' => $p->status,
                'experiences_count' => $p->experiences->count(),
                'preview_images' => $p->experiences->pluck('cover_image')->take(3),
                'updated_at' => $p->updated_at?->toISOString(),
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
     * Create a plan
     * POST /v1/plans
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'date' => 'nullable|date|after_or_equal:today',
            'family_id' => 'nullable|uuid|exists:families,id',
            'experience_ids' => 'nullable|array',
            'experience_ids.*' => 'uuid|exists:experiences,id',
        ]);

        $user = $request->user();

        $plan = DB::transaction(function () use ($request, $user) {
            $plan = Plan::create([
                'user_id' => $user->id,
                'family_id' => $request->family_id ?? $user->getPrimaryFamily()?->id,
                'title' => $request->title,
                'date' => $request->date,
                'status' => 'draft',
                'visibility' => 'private',
            ]);

            if ($request->filled('experience_ids')) {
                foreach ($request->experience_ids as $index => $expId) {
                    PlanExperience::create([
                        'plan_id' => $plan->id,
                        'experience_id' => $expId,
                        'order' => $index,
                    ]);
                }
            }

            return $plan;
        });

        return response()->json([
            'data' => [
                'id' => $plan->id,
                'title' => $plan->title,
                'date' => $plan->date?->format('Y-m-d'),
                'status' => $plan->status,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }

    /**
     * Get plan details
     * GET /v1/plans/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $plan = Plan::with(['experiences.category', 'collaborators.user', 'memories'])
            ->findOrFail($id);

        // Check access
        if (!$this->canAccess($plan, $user)) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Access denied.']],
            ], 403);
        }

        return response()->json([
            'data' => [
                'id' => $plan->id,
                'title' => $plan->title,
                'date' => $plan->date?->format('Y-m-d'),
                'status' => $plan->status,
                'visibility' => $plan->visibility,
                'notes' => $plan->notes,
                'is_owner' => $plan->user_id === $user->id,
                'experiences' => $plan->experiences->map(fn($e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'cover_image' => $e->cover_image,
                    'category' => $e->category?->name,
                    'order' => $e->pivot->order,
                    'time_slot' => $e->pivot->time_slot,
                    'notes' => $e->pivot->notes,
                ]),
                'collaborators' => $plan->collaborators->map(fn($c) => [
                    'id' => $c->user_id,
                    'name' => $c->user?->name,
                    'avatar' => $c->user?->avatar,
                    'role' => $c->role,
                ]),
                'memories_count' => $plan->memories->count(),
                'created_at' => $plan->created_at?->toISOString(),
                'updated_at' => $plan->updated_at?->toISOString(),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Update plan
     * PUT /v1/plans/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'title' => 'nullable|string|max:200',
            'date' => 'nullable|date',
            'status' => 'nullable|in:draft,planned,in_progress,completed',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($id);

        if (!$this->canEdit($plan, $user)) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Cannot edit this plan.']],
            ], 403);
        }

        $plan->update($request->only(['title', 'date', 'status', 'notes']));

        return response()->json([
            'data' => [
                'id' => $plan->id,
                'title' => $plan->title,
                'status' => $plan->status,
                'updated_at' => $plan->updated_at?->toISOString(),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Add experience to plan
     * POST /v1/plans/{id}/experiences
     */
    public function addExperience(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'experience_id' => 'required|uuid|exists:experiences,id',
            'time_slot' => 'nullable|in:morning,afternoon,evening',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($id);

        if (!$this->canEdit($plan, $user)) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Cannot edit this plan.']],
            ], 403);
        }

        $maxOrder = PlanExperience::where('plan_id', $id)->max('order') ?? -1;

        PlanExperience::create([
            'plan_id' => $id,
            'experience_id' => $request->experience_id,
            'order' => $maxOrder + 1,
            'time_slot' => $request->time_slot,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'data' => ['message' => 'Experience added to plan.'],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }

    /**
     * Invite collaborator
     * POST /v1/plans/{id}/collaborators
     */
    public function inviteCollaborator(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'role' => 'nullable|in:editor,viewer',
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($id);

        if (!$plan->isOwner($user)) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Only the owner can invite collaborators.']],
            ], 403);
        }

        PlanCollaborator::updateOrCreate(
            ['plan_id' => $id, 'user_id' => $request->user_id],
            [
                'role' => $request->role ?? 'viewer',
                'invited_at' => now(),
                'invited_by' => $user->id,
            ]
        );

        return response()->json([
            'data' => ['message' => 'Collaborator invited.'],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }

    /**
     * Remove collaborator
     * DELETE /v1/plans/{id}/collaborators/{userId}
     */
    public function removeCollaborator(Request $request, string $id, string $userId): JsonResponse
    {
        $user = $request->user();
        $plan = Plan::findOrFail($id);

        if (!$plan->isOwner($user)) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Only the owner can remove collaborators.']],
            ], 403);
        }

        PlanCollaborator::where('plan_id', $id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json([
            'data' => ['message' => 'Collaborator removed.'],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Update experience in plan
     * PUT /v1/plans/{id}/experiences/{expId}
     */
    public function updateExperience(Request $request, string $id, string $expId): JsonResponse
    {
        $request->validate([
            'order' => 'nullable|integer|min:0',
            'time_slot' => 'nullable|in:morning,afternoon,evening',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($id);

        if (!$this->canEdit($plan, $user)) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Cannot edit this plan.']],
            ], 403);
        }

        $planExp = PlanExperience::where('plan_id', $id)
            ->where('experience_id', $expId)
            ->firstOrFail();

        $planExp->update($request->only(['order', 'time_slot', 'notes']));

        return response()->json([
            'data' => ['message' => 'Experience updated.'],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Remove experience from plan
     * DELETE /v1/plans/{id}/experiences/{expId}
     */
    public function removeExperience(Request $request, string $id, string $expId): JsonResponse
    {
        $user = $request->user();
        $plan = Plan::findOrFail($id);

        if (!$this->canEdit($plan, $user)) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Cannot edit this plan.']],
            ], 403);
        }

        PlanExperience::where('plan_id', $id)
            ->where('experience_id', $expId)
            ->delete();

        return response()->json([
            'data' => ['message' => 'Experience removed from plan.'],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Delete plan
     * DELETE /v1/plans/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $plan = Plan::findOrFail($id);

        if ($plan->user_id !== $user->id) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Only the owner can delete the plan.']],
            ], 403);
        }

        $plan->delete();

        return response()->json([
            'data' => ['message' => 'Plan deleted.'],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Mark plan as complete
     * POST /v1/plans/{id}/complete
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $plan = Plan::findOrFail($id);

        if (!$this->canEdit($plan, $user)) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Cannot edit this plan.']],
            ], 403);
        }

        $plan->update(['status' => 'completed']);

        return response()->json([
            'data' => [
                'id' => $plan->id,
                'status' => $plan->status,
                'message' => 'Plan marked as completed!',
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Duplicate plan
     * POST /v1/plans/{id}/duplicate
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $original = Plan::with('experiences')->findOrFail($id);

        if (!$this->canAccess($original, $user)) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Cannot access this plan.']],
            ], 403);
        }

        $newPlan = DB::transaction(function () use ($original, $user, $request) {
            $plan = Plan::create([
                'user_id' => $user->id,
                'family_id' => $user->getPrimaryFamily()?->id,
                'title' => $original->title . ' (cópia)',
                'date' => $request->input('date'),
                'status' => 'draft',
                'visibility' => 'private',
            ]);

            foreach ($original->experiences as $exp) {
                PlanExperience::create([
                    'plan_id' => $plan->id,
                    'experience_id' => $exp->id,
                    'order' => $exp->pivot->order,
                    'time_slot' => $exp->pivot->time_slot,
                    'notes' => $exp->pivot->notes,
                ]);
            }

            return $plan;
        });

        return response()->json([
            'data' => [
                'id' => $newPlan->id,
                'title' => $newPlan->title,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }

    protected function canAccess(Plan $plan, $user): bool
    {
        return $plan->user_id === $user->id || $plan->hasCollaborator($user);
    }

    protected function canEdit(Plan $plan, $user): bool
    {
        if ($plan->user_id === $user->id) {
            return true;
        }

        $collaborator = $plan->collaborators()->where('user_id', $user->id)->first();
        return $collaborator && $collaborator->canEdit();
    }
}

