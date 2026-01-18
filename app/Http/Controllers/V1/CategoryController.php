<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\CacheHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group 8. Categorias
 *
 * Endpoints para listar categorias de experiências.
 *
 * As categorias são usadas para classificar e filtrar experiências.
 * Este endpoint é público e os dados são cacheados por 24 horas.
 */
class CategoryController extends Controller
{
    /**
     * Listar Categorias
     *
     * Retorna todas as categorias ativas ordenadas por prioridade.
     * Os dados são cacheados por 24 horas para melhor performance.
     *
     * @unauthenticated
     *
     * @response 200 scenario="Lista de categorias" {
     *   "data": [
     *     {
     *       "id": "c038d7b3-74b9-4c28-8488-b64a5dc1d791",
     *       "name": "Parques",
     *       "slug": "parques",
     *       "emoji": "🌳",
     *       "icon": "trees",
     *       "color": "#22c55e",
     *       "description": "Parques, praças e áreas verdes para curtir ao ar livre",
     *       "experiences_count": 45
     *     },
     *     {
     *       "id": "99da4ce7-cf82-4445-9942-51873a2c7741",
     *       "name": "Museus",
     *       "slug": "museus",
     *       "emoji": "🏛️",
     *       "icon": "landmark",
     *       "color": "#8b5cf6",
     *       "description": "Museus, exposições e centros culturais",
     *       "experiences_count": 32
     *     }
     *   ],
     *   "meta": {
     *     "success": true,
     *     "cache_until": "2026-01-19T10:00:00Z"
     *   },
     *   "errors": null
     * }
     *
     * @responseField id string UUID da categoria.
     * @responseField name string Nome da categoria em português.
     * @responseField slug string Identificador URL-friendly.
     * @responseField emoji string Emoji representativo.
     * @responseField icon string Nome do ícone (para uso com bibliotecas de ícones).
     * @responseField color string Cor em hexadecimal (#RRGGBB).
     * @responseField description string Descrição breve da categoria.
     * @responseField experiences_count integer Quantidade de experiências ativas nesta categoria.
     */
    public function index(Request $request): JsonResponse
    {
        // Cache categories for 24 hours (they rarely change)
        $categories = CacheHelper::remember(
            'categories:all',
            86400,
            fn() => Category::active()
                ->orderBy('order')
                ->withCount('experiences')
                ->get(),
            ['categories']
        );

        return response()->json([
            'data' => $categories->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'emoji' => $c->emoji,
                'icon' => $c->icon,
                'color' => $c->color,
                'description' => $c->description,
                'experiences_count' => $c->experiences_count ?? 0,
            ]),
            'meta' => [
                'success' => true,
                'cache_until' => now()->addDay()->toISOString(),
            ],
            'errors' => null,
        ])->header('Cache-Control', 'public, max-age=86400');
    }
}
