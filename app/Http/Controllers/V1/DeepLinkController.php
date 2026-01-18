<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Experience;
use App\Models\Family;
use App\Models\FamilyInvite;
use App\Models\Plan;
use App\Models\ShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeepLinkController extends Controller
{
    /**
     * Resolve a deep link code
     * GET /v1/resolve/{code}
     */
    public function resolve(Request $request, string $code): JsonResponse
    {
        // Check share links first
        $shareLink = ShareLink::where('code', $code)->first();

        if ($shareLink) {
            // Increment click count
            $shareLink->increment('clicks_count');

            $redirectUrl = match ($shareLink->type) {
                'experience' => "/experiences/{$shareLink->target_id}",
                'plan' => "/plans/{$shareLink->target_id}",
                'family' => "/family/{$shareLink->target_id}",
                'invite' => "/family/join?code={$code}",
                default => '/',
            };

            // Validate target exists
            $targetExists = match ($shareLink->type) {
                'experience' => Experience::where('id', $shareLink->target_id)->exists(),
                'plan' => Plan::where('id', $shareLink->target_id)->exists(),
                'family' => Family::where('id', $shareLink->target_id)->exists(),
                'invite' => FamilyInvite::where('id', $shareLink->target_id)->exists(),
                default => false,
            };

            if (!$targetExists) {
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => [['code' => 'TARGET_NOT_FOUND', 'message' => 'Content not found or has been removed.']],
                ], 404);
            }

            return response()->json([
                'data' => [
                    'type' => $shareLink->type,
                    'target_id' => $shareLink->target_id,
                    'redirect_url' => $redirectUrl,
                ],
                'meta' => ['success' => true],
                'errors' => null,
            ]);
        }

        // Check family invite codes
        $invite = FamilyInvite::where('code', $code)->first();

        if ($invite) {
            if (!$invite->isValid()) {
                return response()->json([
                    'data' => null,
                    'meta' => ['success' => false],
                    'errors' => [['code' => 'INVITE_EXPIRED', 'message' => 'This invite has expired or reached maximum uses.']],
                ], 400);
            }

            $family = Family::find($invite->family_id);

            return response()->json([
                'data' => [
                    'type' => 'family_invite',
                    'target_id' => $invite->id,
                    'redirect_url' => "/family/join?code={$code}",
                    'preview' => [
                        'family_name' => $family?->name,
                        'family_avatar' => $family?->avatar,
                    ],
                ],
                'meta' => ['success' => true],
                'errors' => null,
            ]);
        }

        return response()->json([
            'data' => null,
            'meta' => ['success' => false],
            'errors' => [['code' => 'LINK_NOT_FOUND', 'message' => 'Invalid or expired link.']],
        ], 404);
    }
}
