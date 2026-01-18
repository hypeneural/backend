<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    /**
     * List all active categories
     * GET /v1/categories
     */
    public function index(Request $request): JsonResponse
    {
        // Cache categories for 24 hours (they rarely change)
        $categories = Cache::tags(['categories'])
            ->remember('categories:all', 86400, function () {
                return Category::active()
                    ->orderBy('order')
                    ->withCount('experiences')
                    ->get();
            });

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
