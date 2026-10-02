<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RoadGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RouteController extends Controller
{
    public const VALID_PROFILES = ['driving', 'walking', 'cycling'];

    public function __construct(private readonly RoadGraphService $roadGraph)
    {
    }

    public function route(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_lat' => ['required', 'numeric', 'between:-90,90'],
            'start_lng' => ['required', 'numeric', 'between:-180,180'],
            'dest_lat' => ['required', 'numeric', 'between:-90,90'],
            'dest_lng' => ['required', 'numeric', 'between:-180,180'],
            'profile' => ['nullable', 'string', Rule::in(self::VALID_PROFILES)],
            'local' => ['nullable', 'boolean'],
        ]);

        $startLat = (float) $validated['start_lat'];
        $startLng = (float) $validated['start_lng'];
        $destLat = (float) $validated['dest_lat'];
        $destLng = (float) $validated['dest_lng'];
        $activeProfile = $validated['profile'] ?? 'driving';
        $forceLocal = $request->boolean('local');

        // Cache base route geometry across all profiles by coordinates to ensure instantaneous profile switching
        $baseCacheKey = sprintf(
            '%s_base_route_%.4f_%.4f_%.4f_%.4f',
            $forceLocal ? 'local_graph' : 'osrm',
            $startLat,
            $startLng,
            $destLat,
            $destLng
        );

        $baseRoute = Cache::remember($baseCacheKey, 600, function () use ($startLat, $startLng, $destLat, $destLng, $forceLocal) {
            if ($forceLocal) {
                $local = $this->roadGraph->findRoute($startLat, $startLng, $destLat, $destLng);
                if ($local) {
                    $drivingSec = (int) max(30, round($local['distance_meters'] / (30000 / 3600)));
                    return [
                        'distance_meters' => $local['distance_meters'],
                        'driving_duration_seconds' => $drivingSec,
                        'coordinates' => $local['coordinates'],
                        'road_names' => $local['road_names'] ?? [],
                        'source' => 'local_graph',
                        'is_fallback' => false,
                    ];
                }
            }
            return $this->fetchRoute($startLat, $startLng, $destLat, $destLng);
        });

        $result = $this->buildRoutePayload(
            (float) $baseRoute['distance_meters'],
            (int) $baseRoute['driving_duration_seconds'],
            $baseRoute['coordinates'],
            $activeProfile,
            (bool) ($baseRoute['is_fallback'] ?? false),
            $baseRoute['source'] ?? 'osrm',
            $baseRoute['road_names'] ?? []
        );

        return response()->json(['data' => $result]);
    }

    private function fetchRoute(float $startLat, float $startLng, float $destLat, float $destLng): array
    {
        $url = sprintf(
            'https://router.project-osrm.org/route/v1/driving/%.6f,%.6f;%.6f,%.6f?overview=full&geometries=geojson',
            $startLng,
            $startLat,
            $destLng,
            $destLat
        );

        try {
            $response = Http::timeout(2.5)->get($url);

            if ($response->successful()) {
                $body = $response->json();
                if (($body['code'] ?? '') === 'Ok' && !empty($body['routes'][0])) {
                    $route = $body['routes'][0];
                    $distanceMeters = (float) ($route['distance'] ?? 0);
                    $osrmDurationSeconds = (int) round($route['duration'] ?? 0);
                    $coordinates = $route['geometry']['coordinates'] ?? [];

                    return [
                        'distance_meters' => $distanceMeters,
                        'driving_duration_seconds' => $osrmDurationSeconds,
                        'coordinates' => $coordinates,
                        'road_names' => [],
                        'source' => 'osrm',
                        'is_fallback' => false,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OSRM route fetch failed, using local topological road graph', ['error' => $e->getMessage()]);
        }

        // Primary resilient fallback: use the Local Topological Road Graph!
        $localRoute = $this->roadGraph->findRoute($startLat, $startLng, $destLat, $destLng);
        if ($localRoute) {
            $drivingDurationSeconds = (int) max(30, round($localRoute['distance_meters'] / (30000 / 3600)));
            return [
                'distance_meters' => $localRoute['distance_meters'],
                'driving_duration_seconds' => $drivingDurationSeconds,
                'coordinates' => $localRoute['coordinates'],
                'road_names' => $localRoute['road_names'] ?? [],
                'source' => 'local_graph',
                'is_fallback' => false,
            ];
        }

        // Extreme tertiary fallback: calculate Haversine distance and straight-line GeoJSON geometry
        $distanceMeters = $this->haversineDistance($startLat, $startLng, $destLat, $destLng);
        $drivingDurationSeconds = (int) round($distanceMeters / (30000 / 3600)); // Estimated 30 km/h in-town speed
        $coordinates = [
            [$startLng, $startLat],
            [$destLng, $destLat],
        ];

        return [
            'distance_meters' => $distanceMeters,
            'driving_duration_seconds' => $drivingDurationSeconds,
            'coordinates' => $coordinates,
            'road_names' => [],
            'source' => 'euclidean',
            'is_fallback' => true,
        ];
    }

    private function buildRoutePayload(
        float $distanceMeters,
        int $drivingDurationSeconds,
        array $coordinates,
        string $activeProfile,
        bool $isFallback = false,
        string $source = 'osrm',
        array $roadNames = []
    ): array {
        // Multi-modal travel time calculations
        $walkDurationSeconds = (int) max(60, round($distanceMeters / (5000 / 3600)));   // 5 km/h walking speed
        $cycleDurationSeconds = (int) max(45, round($distanceMeters / (15000 / 3600))); // 15 km/h cycling speed
        $driveDurationSeconds = max(30, $drivingDurationSeconds);

        $durations = [
            'driving' => [
                'seconds' => $driveDurationSeconds,
                'text' => $this->formatDuration($driveDurationSeconds),
                'label' => 'Drive',
                'icon' => 'ph-car',
            ],
            'walking' => [
                'seconds' => $walkDurationSeconds,
                'text' => $this->formatDuration($walkDurationSeconds),
                'label' => 'Walk',
                'icon' => 'ph-person-simple-walk',
            ],
            'cycling' => [
                'seconds' => $cycleDurationSeconds,
                'text' => $this->formatDuration($cycleDurationSeconds),
                'label' => 'Cycle',
                'icon' => 'ph-bicycle',
            ],
        ];

        $currentDuration = $durations[$activeProfile] ?? $durations['driving'];

        return [
            'profile' => $activeProfile,
            'distance_meters' => round($distanceMeters, 1),
            'distance_text' => $this->formatDistance($distanceMeters),
            'duration_seconds' => $currentDuration['seconds'],
            'duration_text' => $currentDuration['text'],
            'durations' => $durations,
            'source' => $source,
            'road_names' => $roadNames,
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $coordinates,
            ],
            'is_fallback' => $isFallback,
        ];
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lngDelta / 2) * sin($lngDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return '< 1 min';
        }
        $minutes = (int) round($seconds / 60);
        if ($minutes < 60) {
            return "{$minutes} min" . ($minutes > 1 ? 's' : '');
        }
        $hours = (int) floor($minutes / 60);
        $remainingMin = $minutes % 60;
        return $remainingMin > 0 ? "{$hours}h {$remainingMin}m" : "{$hours}h";
    }

    private function formatDistance(float $meters): string
    {
        if ($meters < 1000) {
            return round($meters) . ' m';
        }
        return number_format($meters / 1000, 1) . ' km';
    }
}
