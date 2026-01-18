<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Generate presigned URL for direct S3 upload
     * POST /v1/uploads/presign
     */
    public function presign(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:memory,review,avatar,family_avatar',
            'content_type' => 'required|in:image/jpeg,image/png,image/webp',
            'filename' => 'required|string|max:255',
        ]);

        $user = $request->user();

        // Generate unique filename
        $extension = match ($request->content_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $uuid = Str::uuid();
        $folder = $request->type . 's'; // memories, reviews, avatars, family_avatars
        $key = "{$folder}/{$user->id}/{$uuid}.{$extension}";

        // Check if S3 is configured
        $disk = config('filesystems.default');

        if ($disk === 's3') {
            // Generate presigned URL for S3
            $uploadUrl = Storage::disk('s3')->temporaryUploadUrl(
                $key,
                now()->addMinutes(15),
                [
                    'ContentType' => $request->content_type,
                    'ACL' => 'public-read',
                ]
            );

            $fileUrl = Storage::disk('s3')->url($key);

            return response()->json([
                'data' => [
                    'upload_url' => $uploadUrl['url'],
                    'file_url' => $fileUrl,
                    'fields' => $uploadUrl['headers'] ?? [],
                    'key' => $key,
                    'expires_at' => now()->addMinutes(15)->toISOString(),
                ],
                'meta' => ['success' => true],
                'errors' => null,
            ]);
        }

        // Fallback for local development - return a mock response
        $baseUrl = config('app.url');
        $fileUrl = "{$baseUrl}/storage/{$key}";

        return response()->json([
            'data' => [
                'upload_url' => "{$baseUrl}/api/v1/uploads/local",
                'file_url' => $fileUrl,
                'fields' => [
                    'key' => $key,
                    'content_type' => $request->content_type,
                ],
                'key' => $key,
                'expires_at' => now()->addMinutes(15)->toISOString(),
                'mode' => 'local', // Indicator for frontend
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ]);
    }

    /**
     * Local file upload (development only)
     * POST /v1/uploads/local
     */
    public function uploadLocal(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:10240', // 10MB
            'key' => 'required|string',
        ]);

        $file = $request->file('file');
        $key = $request->input('key');

        // Store file locally
        $path = $file->storeAs(
            dirname($key),
            basename($key),
            'public'
        );

        $fileUrl = Storage::disk('public')->url($path);

        return response()->json([
            'data' => [
                'file_url' => $fileUrl,
                'key' => $key,
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }
}
