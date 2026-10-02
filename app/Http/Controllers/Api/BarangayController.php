<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CachesJsonResponses;
use App\Http\Controllers\Controller;
use App\Models\BoundaryArea;
use App\Services\AtlasCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarangayController extends Controller
{
    use CachesJsonResponses;

    public function __construct(private readonly AtlasCache $atlas)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $payload = $this->atlas->barangays(fn () => [
            'data' => BoundaryArea::query()
                ->barangays()
                ->withCount(['places as place_count' => fn ($query) => $query->where('status', 'approved')])
                ->orderBy('name')
                ->get()
                ->map(fn (BoundaryArea $barangay) => [
                    'id' => $barangay->id,
                    'name' => $barangay->name,
                    'type' => $barangay->type,
                    'psgc' => $barangay->psgc,
                    'place_count' => $barangay->place_count,
                ])
                ->values(),
        ]);

        return $this->cachedJson($request, $payload, 300);
    }

    public function boundaries(Request $request): JsonResponse
    {
        $payload = $this->atlas->boundaries(fn () => [
            'data' => [
                'type' => 'FeatureCollection',
                'features' => BoundaryArea::query()
                    ->barangays()
                    ->whereNotNull('geom')
                    ->select(['id', 'name', 'type', 'psgc', 'boundary_source'])
                    ->selectRaw('ST_AsGeoJSON(geom, 6) AS geojson')
                    ->orderBy('name')
                    ->get()
                    ->map(function (BoundaryArea $area) {
                        $geometry = $area->geojson ? json_decode($area->geojson, true) : null;
                        if (! is_array($geometry)) {
                            return null;
                        }

                        return [
                            'type' => 'Feature',
                            'geometry' => $geometry,
                            'properties' => [
                                'id' => (int) $area->id,
                                'name' => $area->name,
                                'type' => $area->type,
                                'psgc' => $area->psgc,
                                'boundary_source' => $area->boundary_source,
                            ],
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all(),
            ],
        ]);

        return $this->cachedJson($request, $payload, 300);
    }
}
