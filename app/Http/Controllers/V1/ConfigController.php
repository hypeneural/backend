<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @group 18. Utilidades
 *
 * Endpoints de utilidade geral como health check e configurações.
 */
class ConfigController extends Controller
{
    /**
     * Health Check
     *
     * Verifica se a API está online e funcionando.
     * Útil para monitoramento e load balancers.
     *
     * @unauthenticated
     *
     * @response 200 scenario="API online" {
     *   "status": "ok",
     *   "timestamp": "2026-01-18T03:15:00Z",
     *   "version": "1.0.0"
     * }
     *
     * @response 503 scenario="API com problemas" {
     *   "status": "error",
     *   "message": "Database connection failed"
     * }
     */
    public function health(): JsonResponse
    {
        try {
            // Test database connection
            DB::connection()->getPdo();

            return response()->json([
                'status' => 'ok',
                'timestamp' => now()->toISOString(),
                'version' => '1.0.0',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Database connection failed',
            ], 503);
        }
    }

    /**
     * Configurações do App
     *
     * Retorna configurações estáticas usadas pelo app.
     * Inclui níveis de energia, opções de vibe, filtros rápidos, etc.
     * Os dados são cacheados por 24 horas.
     *
     * @unauthenticated
     *
     * @response 200 scenario="Configurações" {
     *   "data": {
     *     "energy_levels": [
     *       {"value": 1, "emoji": "😴", "label": "Dia de algo levinho"},
     *       {"value": 2, "emoji": "🙂", "label": "Passeio tranquilo"},
     *       {"value": 3, "emoji": "😄", "label": "Prontos pra ação"},
     *       {"value": 4, "emoji": "🤩", "label": "Aventura total"},
     *       {"value": 5, "emoji": "🚀", "label": "Energia máxima!"}
     *     ],
     *     "vibe_options": [
     *       {"id": "relaxing", "emoji": "😌", "label": "Relaxante"},
     *       {"id": "adventure", "emoji": "🎢", "label": "Aventura"},
     *       {"id": "educational", "emoji": "📚", "label": "Educativo"},
     *       {"id": "fun", "emoji": "🎉", "label": "Divertido"},
     *       {"id": "romantic", "emoji": "💕", "label": "Romântico"}
     *     ],
     *     "quick_filters": [
     *       {"id": "adventure", "label": "Aventura", "emoji": "🎢", "search_params": {"categories": ["uuid"]}},
     *       {"id": "rain", "label": "Dia de chuva", "emoji": "🌧️", "search_params": {"weather": "rain"}},
     *       {"id": "baby", "label": "Com bebê", "emoji": "👶", "search_params": {"age_tags": ["baby"]}},
     *       {"id": "free", "label": "Grátis", "emoji": "🆓", "search_params": {"price": ["free"]}},
     *       {"id": "food", "label": "Comida", "emoji": "🍕", "search_params": {"has_food": true}},
     *       {"id": "quick", "label": "Rapidinho", "emoji": "⚡", "search_params": {"duration": "quick"}}
     *     ],
     *     "age_groups": [
     *       {"value": "baby", "label": "Bebê", "emoji": "👶", "age_range": "0-1 ano"},
     *       {"value": "toddler", "label": "Criança pequena", "emoji": "🧒", "age_range": "2-4 anos"},
     *       {"value": "kid", "label": "Criança", "emoji": "👦", "age_range": "5-12 anos"},
     *       {"value": "teen", "label": "Adolescente", "emoji": "🧑", "age_range": "13-17 anos"}
     *     ],
     *     "price_levels": [
     *       {"value": "free", "label": "Grátis", "emoji": "🆓"},
     *       {"value": "moderate", "label": "Moderado", "emoji": "💵", "range": "R$ 20-80"},
     *       {"value": "top", "label": "Premium", "emoji": "💎", "range": "R$ 80+"}
     *     ],
     *     "duration_buckets": [
     *       {"value": "quick", "label": "Rapidinho", "emoji": "⚡", "range": "até 1h"},
     *       {"value": "half", "label": "Meio período", "emoji": "🕐", "range": "1-3h"},
     *       {"value": "full", "label": "Dia inteiro", "emoji": "☀️", "range": "3h+"}
     *     ]
     *   },
     *   "meta": {
     *     "success": true,
     *     "cache_until": "2026-01-19T03:15:00Z"
     *   }
     * }
     */
    public function config(): JsonResponse
    {
        $config = Cache::remember('app:config', 86400, function () {
            return [
                'energy_levels' => [
                    ['value' => 1, 'emoji' => '😴', 'label' => 'Dia de algo levinho'],
                    ['value' => 2, 'emoji' => '🙂', 'label' => 'Passeio tranquilo'],
                    ['value' => 3, 'emoji' => '😄', 'label' => 'Prontos pra ação'],
                    ['value' => 4, 'emoji' => '🤩', 'label' => 'Aventura total'],
                    ['value' => 5, 'emoji' => '🚀', 'label' => 'Energia máxima!'],
                ],
                'vibe_options' => [
                    ['id' => 'relaxing', 'emoji' => '😌', 'label' => 'Relaxante'],
                    ['id' => 'adventure', 'emoji' => '🎢', 'label' => 'Aventura'],
                    ['id' => 'educational', 'emoji' => '📚', 'label' => 'Educativo'],
                    ['id' => 'fun', 'emoji' => '🎉', 'label' => 'Divertido'],
                    ['id' => 'romantic', 'emoji' => '💕', 'label' => 'Romântico'],
                    ['id' => 'creative', 'emoji' => '🎨', 'label' => 'Criativo'],
                    ['id' => 'sporty', 'emoji' => '⚽', 'label' => 'Esportivo'],
                ],
                'quick_filters' => [
                    ['id' => 'adventure', 'label' => 'Aventura', 'emoji' => '🎢', 'search_params' => ['vibe' => 'adventure']],
                    ['id' => 'rain', 'label' => 'Dia de chuva', 'emoji' => '🌧️', 'search_params' => ['weather' => 'rain']],
                    ['id' => 'baby', 'label' => 'Com bebê', 'emoji' => '👶', 'search_params' => ['age_tags' => ['baby']]],
                    ['id' => 'free', 'label' => 'Grátis', 'emoji' => '🆓', 'search_params' => ['price' => ['free']]],
                    ['id' => 'food', 'label' => 'Comida', 'emoji' => '🍕', 'search_params' => ['has_food' => true]],
                    ['id' => 'quick', 'label' => 'Rapidinho', 'emoji' => '⚡', 'search_params' => ['duration' => 'quick']],
                    ['id' => 'outdoor', 'label' => 'Ao ar livre', 'emoji' => '🌳', 'search_params' => ['weather' => 'sun']],
                    ['id' => 'indoor', 'label' => 'Indoor', 'emoji' => '🏠', 'search_params' => ['weather' => 'any']],
                ],
                'age_groups' => [
                    ['value' => 'baby', 'label' => 'Bebê', 'emoji' => '👶', 'age_range' => '0-1 ano'],
                    ['value' => 'toddler', 'label' => 'Criança pequena', 'emoji' => '🧒', 'age_range' => '2-4 anos'],
                    ['value' => 'kid', 'label' => 'Criança', 'emoji' => '👦', 'age_range' => '5-12 anos'],
                    ['value' => 'teen', 'label' => 'Adolescente', 'emoji' => '🧑', 'age_range' => '13-17 anos'],
                ],
                'price_levels' => [
                    ['value' => 'free', 'label' => 'Grátis', 'emoji' => '🆓'],
                    ['value' => 'moderate', 'label' => 'Moderado', 'emoji' => '💵', 'range' => 'R$ 20-80'],
                    ['value' => 'top', 'label' => 'Premium', 'emoji' => '💎', 'range' => 'R$ 80+'],
                ],
                'duration_buckets' => [
                    ['value' => 'quick', 'label' => 'Rapidinho', 'emoji' => '⚡', 'range' => 'até 1h'],
                    ['value' => 'half', 'label' => 'Meio período', 'emoji' => '🕐', 'range' => '1-3h'],
                    ['value' => 'full', 'label' => 'Dia inteiro', 'emoji' => '☀️', 'range' => '3h+'],
                ],
                'notification_types' => [
                    'family_invite' => 'Convites para família',
                    'memory_reaction' => 'Reações em memórias',
                    'plan_reminder' => 'Lembretes de planos',
                    'trending' => 'Experiências em alta',
                    'badge_earned' => 'Conquistas',
                    'plan_update' => 'Atualizações de planos',
                    'new_review' => 'Novas reviews',
                ],
            ];
        });

        return response()->json([
            'data' => $config,
            'meta' => [
                'success' => true,
                'cache_until' => now()->addDay()->toISOString(),
            ],
            'errors' => null,
        ])->header('Cache-Control', 'public, max-age=86400');
    }
}
