<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Submit a report
     * POST /v1/reports
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:experience,review,memory,user',
            'target_id' => 'required|uuid',
            'reason' => 'required|in:inappropriate,spam,wrong_info,closed,harassment,other',
            'details' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        // Check if already reported
        $existing = Report::where('reporter_id', $user->id)
            ->where('reportable_type', $this->getReportableType($request->type))
            ->where('reportable_id', $request->target_id)
            ->first();

        if ($existing) {
            return response()->json([
                'data' => null,
                'meta' => ['success' => false],
                'errors' => [['message' => 'You have already reported this item.']],
            ], 409);
        }

        $report = Report::create([
            'reporter_id' => $user->id,
            'reportable_type' => $this->getReportableType($request->type),
            'reportable_id' => $request->target_id,
            'reason' => $request->reason,
            'details' => $request->details,
            'status' => 'pending',
        ]);

        return response()->json([
            'data' => [
                'id' => $report->id,
                'message' => 'Report submitted. Thank you for helping keep our community safe.',
            ],
            'meta' => ['success' => true],
            'errors' => null,
        ], 201);
    }

    protected function getReportableType(string $type): string
    {
        return match ($type) {
            'experience' => 'App\\Models\\Experience',
            'review' => 'App\\Models\\Review',
            'memory' => 'App\\Models\\Memory',
            'user' => 'App\\Models\\User',
            default => 'App\\Models\\Experience',
        };
    }
}
