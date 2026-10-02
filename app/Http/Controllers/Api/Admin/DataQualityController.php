<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\GeoSpatial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataQualityController extends Controller
{
    public function __construct(
        private readonly GeoSpatial $spatial,
        private readonly AuditService $audit,
    ) {
    }

    public function barangay(Request $request): JsonResponse
    {
        $limit = max(1, min(2000, (int) $request->query('limit', 500)));
        $rows = $this->spatial->barangayMismatchRows($limit);

        return response()->json([
            'data' => $rows,
            'meta' => ['total' => count($rows), 'limit' => $limit],
        ]);
    }

    public function fixBarangay(Request $request): JsonResponse
    {
        $limit = max(1, min(2000, (int) $request->json('limit', 500)));
        $fixed = $this->spatial->fixBarangayMismatch($limit);

        $this->audit->record('boundary_area', 0, 'batch_fix', $request->user()?->id, [
            'affected_count' => $fixed,
        ]);

        return response()->json([
            'data' => ['fixed' => $fixed],
        ]);
    }
}