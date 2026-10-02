<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\PlaceReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlaceReportController extends Controller
{
    public const VALID_ISSUE_TYPES = [
        'wrong_info',
        'closed_permanently',
        'duplicate',
        'wrong_location',
        'inappropriate',
        'other',
    ];

    public function store(Place $place, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'issue_type' => ['required', 'string', Rule::in(self::VALID_ISSUE_TYPES)],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = PlaceReport::create([
            'place_id' => $place->id,
            'user_id' => $request->user()->id,
            'issue_type' => $validated['issue_type'],
            'details' => $validated['details'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'data' => [
                'id' => $report->id,
                'message' => 'Thank you! Your feedback has been submitted to the moderation team.',
            ],
        ], 201);
    }
}
