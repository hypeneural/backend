<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShareLinkController extends Controller
{
    /**
     * Generate share link
     * POST /v1/share-links
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:experience,plan,family,invite',
            'target_id' => 'required|uuid',
            'utm_source' => 'nullable|string|max:50',
            'utm_campaign' => 'nullable|string|max:50',
        ]);

        $user = $request->user();

        // Validate target exists based on type
        $targetExists = match ($request->type) {
            'experience' => \App\Models\Experience::where('id', $request->target_id)->exists(),
            'plan' => \App\Models\Plan::where('id', $request->target_id)->exists(),
            'family' => \App\Models\Family::where('id', $request->target_id)->exists(),
            'invite' => \App\Models\FamilyInvite::where('id', $request->target_id)->exists(),
            default => false,
        };

        if (!$targetExists) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'Target not found.']],
            ], 404);
        }

        $code = ShareLink::generateCode();

        $shareLink = ShareLink::create([
            'type' => $request->type,
            'target_id' => $request->target_id,
            'code' => $code,
            'created_by' => $user->id,
            'expires_at' => now()->addDays(30),
            'clicks_count' => 0,
            'utm_source' => $request->utm_source,
            'utm_campaign' => $request->utm_campaign,
        ]);

        $baseUrl = config('app.url');
        $shortUrl = "{$baseUrl}/s/{$code}";

        return response()->json([
            'data' => [
                'id' => $shareLink->id,
                'code' => $shareLink->code,
                'short_url' => $shortUrl,
                'type' => $shareLink->type,
                'target_id' => $shareLink->target_id,
                'expires_at' => $shareLink->expires_at?->toISOString(),
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }
}
