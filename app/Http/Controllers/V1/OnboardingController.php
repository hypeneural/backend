<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Dependent;
use App\Models\Family;
use App\Models\FamilyPreference;
use App\Models\FamilyPreferenceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    /**
     * Get onboarding status
     * GET /v1/onboarding/status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['stats', 'families.dependents', 'families.preferences']);

        $stepsCompleted = [];
        $missingSteps = [];

        // Check name
        if ($user->name) {
            $stepsCompleted[] = 'name';
        } else {
            $missingSteps[] = 'name';
        }

        // Check family
        $primaryFamily = $user->getPrimaryFamily();
        if ($primaryFamily) {
            $stepsCompleted[] = 'family';

            // Check preferences
            if ($primaryFamily->preferences) {
                $stepsCompleted[] = 'preferences';
            } else {
                $missingSteps[] = 'preferences';
            }

            // Check categories
            $hasCategories = FamilyPreferenceCategory::where('family_id', $primaryFamily->id)->exists();
            if ($hasCategories) {
                $stepsCompleted[] = 'categories';
            } else {
                $missingSteps[] = 'categories';
            }

            // Check dependents (optional but tracked)
            if ($primaryFamily->dependents->isNotEmpty()) {
                $stepsCompleted[] = 'dependents';
            }
        } else {
            $missingSteps[] = 'family';
            $missingSteps[] = 'preferences';
            $missingSteps[] = 'categories';
        }

        $completed = empty($missingSteps) ||
            (count($missingSteps) === 0 ||
                (count($missingSteps) === 1 && !in_array('dependents', $missingSteps)));

        return response()->json([
            'data' => [
                'completed' => $completed,
                'steps_completed' => $stepsCompleted,
                'missing_steps' => $missingSteps,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Complete onboarding
     * POST /v1/onboarding/complete
     */
    public function complete(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'favorite_categories' => 'required|array|min:1',
            'favorite_categories.*' => 'uuid|exists:categories,id',
            'max_distance_km' => 'nullable|integer|min:1|max:100',
            'default_price' => 'nullable|in:free,moderate,top',
            'family_name' => 'nullable|string|max:100',
            'dependents' => 'nullable|array',
            'dependents.*.name' => 'required|string|max:100',
            'dependents.*.birth_date' => 'nullable|date|before:today',
            'dependents.*.age_group' => 'required|in:baby,toddler,kid,teen',
            'dependents.*.avatar' => 'nullable|string|max:10',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            // Update user name
            $user->update(['name' => $request->name]);

            // Get or create family
            $family = $user->getPrimaryFamily();

            if ($request->family_name) {
                $family->update(['name' => $request->family_name]);
            }

            // Create or update preferences
            FamilyPreference::updateOrCreate(
                ['family_id' => $family->id],
                [
                    'max_distance_km' => $request->input('max_distance_km', 30),
                    'default_price' => $request->input('default_price', 'moderate'),
                ]
            );

            // Set favorite categories
            FamilyPreferenceCategory::where('family_id', $family->id)->delete();

            foreach ($request->favorite_categories as $index => $categoryId) {
                FamilyPreferenceCategory::create([
                    'family_id' => $family->id,
                    'category_id' => $categoryId,
                    'weight' => 1.0 - ($index * 0.1), // First category has highest weight
                ]);
            }

            // Add dependents
            if ($request->filled('dependents')) {
                foreach ($request->dependents as $dependent) {
                    Dependent::create([
                        'family_id' => $family->id,
                        'name' => $dependent['name'],
                        'birth_date' => $dependent['birth_date'] ?? null,
                        'age_group' => $dependent['age_group'],
                        'avatar' => $dependent['avatar'] ?? null,
                    ]);
                }
            }

            // Mark onboarding complete
            $user->update(['is_verified' => true]);
        });

        return response()->json([
            'data' => [
                'message' => 'Onboarding completed successfully!',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'onboarding_completed' => true,
                ],
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }
}
