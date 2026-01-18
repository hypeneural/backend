<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     *
     * @unauthenticated
     *
     * @response 200 scenario="API online" {
     *   "status": "ok",
     *   "timestamp": "2026-01-18T03:15:00Z",
     *   "version": "1.0.0"
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
     *
     * @unauthenticated
     */
    public function config(): JsonResponse
    {
        $config = [
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
                ['id' => 'adventure', 'label' => 'Aventura', 'emoji' => '🎢'],
                ['id' => 'rain', 'label' => 'Dia de chuva', 'emoji' => '🌧️'],
                ['id' => 'baby', 'label' => 'Com bebê', 'emoji' => '👶'],
                ['id' => 'free', 'label' => 'Grátis', 'emoji' => '🆓'],
                ['id' => 'food', 'label' => 'Comida', 'emoji' => '🍕'],
                ['id' => 'quick', 'label' => 'Rapidinho', 'emoji' => '⚡'],
                ['id' => 'outdoor', 'label' => 'Ao ar livre', 'emoji' => '🌳'],
                ['id' => 'indoor', 'label' => 'Indoor', 'emoji' => '🏠'],
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
        ];

        return response()->json([
            'data' => $config,
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }
}
