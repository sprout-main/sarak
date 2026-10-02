<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoundaryArea;
use Illuminate\Http\JsonResponse;

class BoundaryController extends Controller
{
    public function index(): JsonResponse
    {
        $areas = BoundaryArea::query()
            ->barangays()
            ->withCount(['places as place_count' => fn ($query) => $query->where('status', 'approved')])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $areas->map(fn (BoundaryArea $area) => [
                'id' => $area->id,
                'name' => $area->name,
                'type' => $area->type,
                'psgc' => $area->psgc,
                'boundary_source' => $area->boundary_source,
                'imported_at' => $area->imported_at?->toISOString(),
                'place_count' => $area->place_count,
            ]),
        ]);
    }
}