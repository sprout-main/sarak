<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        $places = Place::approved()
            ->with(['category', 'barangay'])
            ->search($validated['q'])
            ->orderBy('name')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => $places->map(fn (Place $place) => [
                'id' => $place->id,
                'name' => $place->name,
                'slug' => $place->slug,
                'category' => $place->category !== null ? [
                    'id' => $place->category->id,
                    'slug' => $place->category->slug,
                    'label' => $place->category->label,
                ] : null,
                'barangay' => $place->barangay !== null ? [
                    'id' => $place->barangay->id,
                    'name' => $place->barangay->name,
                ] : null,
                'lat' => $place->lat,
                'lng' => $place->lng,
            ])->all(),
        ]);
    }

    public function semantic(Request $request, \App\Services\SemanticSearchService $semantic): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $lat = isset($validated['lat']) ? (float) $validated['lat'] : null;
        $lng = isset($validated['lng']) ? (float) $validated['lng'] : null;
        $limit = isset($validated['limit']) ? (int) $validated['limit'] : 10;

        $results = $semantic->search($validated['q'], $lat, $lng, $limit);

        return response()->json([
            'data' => $results,
        ]);
    }
}