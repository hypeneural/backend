<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ExperienceSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    /**
     * Get experiences for map view with bbox and clustering
     * GET /v1/map/experiences?bbox=w,s,e,n&zoom=z&limit=100
     */
    public function experiences(Request $request): JsonResponse
    {
        $request->validate([
            'bbox' => 'required|string',
            'zoom' => 'required|integer|min:1|max:22',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        // Parse bbox (west,south,east,north)
        $bboxParts = explode(',', $request->bbox);
        if (count($bboxParts) !== 4) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Invalid bbox format. Use: west,south,east,north']],
            ], 400);
        }

        [$west, $south, $east, $north] = array_map('floatval', $bboxParts);
        $zoom = $request->integer('zoom');
        $limit = $request->integer('limit', 100);

        // Query experiences in bbox
        $query = ExperienceSearch::query()
            ->where('status', 'published')
            ->inBbox($west, $south, $east, $north);

        // High zoom (>= 14): return individual points
        if ($zoom >= 14) {
            $experiences = $query->limit($limit)->get();

            $points = $experiences->map(fn($exp) => [
                'id' => $exp->experience_id,
                'lat' => (float) $exp->lat,
                'lng' => (float) $exp->lng,
                'title' => $exp->title,
                'cover_image' => $exp->cover_image,
                'price_level' => $exp->price_level,
                'average_rating' => $exp->average_rating,
            ]);

            return response()->json([
                'data' => [
                    'points' => $points,
                    'clusters' => [],
                    'total' => $query->count(),
                ],
                'meta' => ['success' => true],
                'errors' => null,
            ]);
        }

        // Low zoom (< 14): cluster by grid
        $gridSize = $this->getGridSize($zoom);
        $clusters = $this->clusterByGrid($query, $gridSize, $limit);

        return response()->json([
            'data' => [
                'points' => [],
                'clusters' => $clusters,
                'total' => $query->count(),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    protected function getGridSize(int $zoom): float
    {
        // Return degrees per grid cell based on zoom level
        return match (true) {
            $zoom <= 5 => 5.0,
            $zoom <= 8 => 2.0,
            $zoom <= 10 => 1.0,
            $zoom <= 12 => 0.5,
            default => 0.25,
        };
    }

    protected function clusterByGrid($query, float $gridSize, int $limit): array
    {
        // Use SQL to group by grid cells
        $results = $query->selectRaw("
            FLOOR(lat / ?) * ? as grid_lat,
            FLOOR(lng / ?) * ? as grid_lng,
            COUNT(*) as count,
            MIN(lat) as min_lat,
            MAX(lat) as max_lat,
            MIN(lng) as min_lng,
            MAX(lng) as max_lng
        ", [$gridSize, $gridSize, $gridSize, $gridSize])
            ->groupByRaw('FLOOR(lat / ?), FLOOR(lng / ?)', [$gridSize, $gridSize])
            ->orderByDesc('count')
            ->limit($limit)
            ->get();

        return $results->map(fn($r) => [
            'lat' => (float) $r->grid_lat + ($gridSize / 2),
            'lng' => (float) $r->grid_lng + ($gridSize / 2),
            'count' => (int) $r->count,
            'bounds' => [
                'west' => (float) $r->min_lng,
                'south' => (float) $r->min_lat,
                'east' => (float) $r->max_lng,
                'north' => (float) $r->max_lat,
            ],
        ])->all();
    }
}
