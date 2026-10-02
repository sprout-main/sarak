<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BarangayResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoUtilityController extends Controller
{
    public function __construct(
        private readonly BarangayResolver $resolver,
    ) {
    }

    public function resolveBarangay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $area = $this->resolver->resolve((float) $validated['lat'], (float) $validated['lng']);

        return response()->json([
            'data' => $area !== null ? [
                'id' => $area->id,
                'name' => $area->name,
                'type' => $area->type,
                'psgc' => $area->psgc,
            ] : null,
        ]);
    }
}