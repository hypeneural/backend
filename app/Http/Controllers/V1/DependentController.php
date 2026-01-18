<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Dependent;
use App\Models\Family;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DependentController extends Controller
{
    /**
     * List dependents in family
     * GET /v1/family/dependents
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $family = $user->getPrimaryFamily();

        if (!$family) {
            return response()->json([
                'data' => [],
                'meta' => ['success' => true],
                'errors' => null,
            ]);
        }

        $dependents = Dependent::where('family_id', $family->id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $dependents->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'birth_date' => $d->birth_date?->format('Y-m-d'),
                'age_group' => $d->age_group,
                'avatar' => $d->avatar,
                'restrictions' => $d->restrictions ?? [],
                'interests' => $d->interests ?? [],
                'age_years' => $d->birth_date ? $d->birth_date->age : null,
                'created_at' => $d->created_at?->toISOString(),
            ]),
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Add dependent
     * POST /v1/family/dependents
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'birth_date' => 'nullable|date|before:today',
            'age_group' => 'required|in:baby,toddler,kid,teen',
            'avatar' => 'nullable|string|max:10',
            'restrictions' => 'nullable|array',
            'restrictions.*' => 'string|max:50',
            'interests' => 'nullable|array',
            'interests.*' => 'uuid|exists:categories,id',
        ]);

        $user = $request->user();
        $family = $user->getPrimaryFamily();

        if (!$family) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'No family found.']],
            ], 400);
        }

        $dependent = Dependent::create([
            'family_id' => $family->id,
            'name' => $request->name,
            'birth_date' => $request->birth_date,
            'age_group' => $request->age_group,
            'avatar' => $request->avatar,
            'restrictions' => $request->restrictions,
            'interests' => $request->interests,
        ]);

        return response()->json([
            'data' => [
                'id' => $dependent->id,
                'name' => $dependent->name,
                'age_group' => $dependent->age_group,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }

    /**
     * Update dependent
     * PUT /v1/family/dependents/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date|before:today',
            'age_group' => 'nullable|in:baby,toddler,kid,teen',
            'avatar' => 'nullable|string|max:10',
            'restrictions' => 'nullable|array',
            'interests' => 'nullable|array',
        ]);

        $user = $request->user();
        $family = $user->getPrimaryFamily();

        $dependent = Dependent::where('id', $id)
            ->where('family_id', $family?->id)
            ->firstOrFail();

        $dependent->update($request->only([
            'name',
            'birth_date',
            'age_group',
            'avatar',
            'restrictions',
            'interests'
        ]));

        return response()->json([
            'data' => [
                'id' => $dependent->id,
                'name' => $dependent->name,
                'age_group' => $dependent->age_group,
                'updated_at' => $dependent->updated_at?->toISOString(),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Delete dependent
     * DELETE /v1/family/dependents/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $family = $user->getPrimaryFamily();

        $dependent = Dependent::where('id', $id)
            ->where('family_id', $family?->id)
            ->firstOrFail();

        $dependent->delete();

        return response()->json([
            'data' => ['message' => 'Dependent removed.'],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }
}
