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

/**
 * @group 2. Onboarding
 *
 * Endpoints para o fluxo de onboarding de novos usuários.
 *
 * Após o primeiro login, o usuário deve completar o onboarding para
 * personalizar sua experiência. O fluxo inclui:
 * 1. Informar nome
 * 2. Criar/nomear família
 * 3. Selecionar categorias favoritas
 * 4. Definir preferências (distância, preço)
 * 5. Adicionar dependentes (opcional)
 */
class OnboardingController extends Controller
{
    /**
     * Status do Onboarding
     *
     * Verifica quais etapas do onboarding o usuário já completou
     * e quais ainda estão pendentes.
     *
     * @authenticated
     *
     * @response 200 scenario="Onboarding incompleto" {
     *   "data": {
     *     "completed": false,
     *     "steps_completed": ["name", "family"],
     *     "missing_steps": ["preferences", "categories"]
     *   },
     *   "meta": {"success": true},
     *   "errors": null
     * }
     *
     * @response 200 scenario="Onboarding completo" {
     *   "data": {
     *     "completed": true,
     *     "steps_completed": ["name", "family", "preferences", "categories", "dependents"],
     *     "missing_steps": []
     *   },
     *   "meta": {"success": true},
     *   "errors": null
     * }
     *
     * @responseField completed boolean Se todas as etapas obrigatórias foram completadas.
     * @responseField steps_completed string[] Lista de etapas já completadas.
     * @responseField missing_steps string[] Lista de etapas pendentes.
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
     * Completar Onboarding
     *
     * Completa todas as etapas do onboarding de uma vez.
     * Este endpoint executa as seguintes ações em uma transação:
     * - Atualiza o nome do usuário
     * - Atualiza o nome da família (se fornecido)
     * - Cria as preferências da família
     * - Associa as categorias favoritas
     * - Cria os dependentes (se fornecidos)
     * - Marca o onboarding como concluído
     *
     * @authenticated
     *
     * @bodyParam name string required Nome do usuário. Example: João Silva
     * @bodyParam favorite_categories string[] required Lista de UUIDs das categorias favoritas (mínimo 1). Example: ["c038d7b3-74b9-4c28-8488-b64a5dc1d791"]
     * @bodyParam max_distance_km integer Distância máxima em km para buscar experiências (1-100). Default: 30. Example: 30
     * @bodyParam default_price string Faixa de preço preferida: `free`, `moderate`, `top`. Default: moderate. Example: moderate
     * @bodyParam family_name string Nome da família. Example: Família Silva
     * @bodyParam dependents object[] Lista de dependentes (crianças).
     * @bodyParam dependents[].name string required Nome do dependente. Example: Lucas
     * @bodyParam dependents[].birth_date string Data de nascimento (YYYY-MM-DD). Example: 2018-05-15
     * @bodyParam dependents[].age_group string required Faixa etária: `baby` (0-1), `toddler` (2-4), `kid` (5-12), `teen` (13-17). Example: kid
     * @bodyParam dependents[].avatar string Emoji para avatar do dependente. Example: 👦
     *
     * @response 201 scenario="Onboarding completado" {
     *   "data": {
     *     "message": "Onboarding completed successfully!",
     *     "user": {
     *       "id": "019bcf92-ecda-70a6-98ec-204362b9c61a",
     *       "name": "João Silva",
     *       "onboarding_completed": true
     *     }
     *   },
     *   "meta": {"success": true},
     *   "errors": null
     * }
     *
     * @response 422 scenario="Dados inválidos" {
     *   "data": null,
     *   "meta": {"success": false},
     *   "errors": [
     *     {"code": "VALIDATION_ERROR", "field": "favorite_categories", "message": "O campo favorite_categories é obrigatório"}
     *   ]
     * }
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
