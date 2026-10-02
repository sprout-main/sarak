<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouteDirectionsTest extends TestCase
{
    public function test_route_requires_coordinates(): void
    {
        $response = $this->getJson('/api/route');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_lat', 'start_lng', 'dest_lat', 'dest_lng']);
    }

    public function test_route_validates_coordinate_bounds(): void
    {
        $response = $this->getJson('/api/route?start_lat=95&start_lng=200&dest_lat=17.5&dest_lng=120.6');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_lat', 'start_lng']);
    }

    public function test_route_returns_route_data_and_geometry(): void
    {
        $response = $this->getJson('/api/route?start_lat=17.589&start_lng=120.614&dest_lat=17.595&dest_lng=120.620&profile=driving');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'profile',
                    'distance_meters',
                    'distance_text',
                    'duration_seconds',
                    'duration_text',
                    'durations' => [
                        'driving' => ['seconds', 'text', 'label', 'icon'],
                        'walking' => ['seconds', 'text', 'label', 'icon'],
                        'cycling' => ['seconds', 'text', 'label', 'icon'],
                    ],
                    'geometry' => [
                        'type',
                        'coordinates',
                    ],
                    'is_fallback',
                ],
            ]);

        $this->assertEquals('driving', $response->json('data.profile'));
        $this->assertGreaterThan(0, $response->json('data.distance_meters'));
        $this->assertNotEmpty($response->json('data.geometry.coordinates'));
    }

    public function test_route_supports_multi_modal_profiles(): void
    {
        $walkingResponse = $this->getJson('/api/route?start_lat=17.589&start_lng=120.614&dest_lat=17.595&dest_lng=120.620&profile=walking');
        $walkingResponse->assertStatus(200);
        $this->assertEquals('walking', $walkingResponse->json('data.profile'));

        $cyclingResponse = $this->getJson('/api/route?start_lat=17.589&start_lng=120.614&dest_lat=17.595&dest_lng=120.620&profile=cycling');
        $cyclingResponse->assertStatus(200);
        $this->assertEquals('cycling', $cyclingResponse->json('data.profile'));
    }

    public function test_route_falls_back_resiliently_when_external_service_fails(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response(null, 500),
        ]);

        $response = $this->getJson('/api/route?start_lat=17.589&start_lng=120.614&dest_lat=17.595&dest_lng=120.620&profile=driving');

        $response->assertStatus(200);

        // When external OSRM fails, the system automatically falls back to the Local Topological Road Graph
        $this->assertEquals('local_graph', $response->json('data.source'));
        $this->assertFalse($response->json('data.is_fallback'));
        $this->assertGreaterThan(2, count($response->json('data.geometry.coordinates')));
        $this->assertNotEmpty($response->json('data.road_names'));
        $this->assertGreaterThan(0, $response->json('data.distance_meters'));
    }

    public function test_searchbox_is_hidden_when_route_is_active(): void
    {
        $searchbox = file_get_contents(resource_path('views/components/map/searchbox.blade.php'));
        $this->assertStringContainsString('x-show="!routeActive"', $searchbox);

        $shell = file_get_contents(resource_path('js/map/map-shell.js'));
        $this->assertStringContainsString('this.routeActive = true;', $shell);
        $this->assertStringContainsString('this.searchOpen = false;', $shell);
    }

    public function test_route_supports_explicit_local_road_graph_parameter(): void
    {
        // Route from Town Plaza (17.5956, 120.6185) north along Taft St to Calaba Hospital (17.6155, 120.6180)
        $response = $this->getJson('/api/route?start_lat=17.5956&start_lng=120.6185&dest_lat=17.6155&dest_lng=120.6180&profile=driving&local=1');

        $response->assertStatus(200)
            ->assertJsonPath('data.source', 'local_graph')
            ->assertJsonPath('data.is_fallback', false);

        $roadNames = $response->json('data.road_names');
        $this->assertIsArray($roadNames);
        $this->assertNotEmpty($roadNames);
        $this->assertStringContainsString('Taft', implode(' ', $roadNames));

        // Must have multiple road coordinate vertices
        $coords = $response->json('data.geometry.coordinates');
        $this->assertGreaterThan(4, count($coords));
        $this->assertGreaterThan(1800, $response->json('data.distance_meters'));
    }

    public function test_local_road_graph_service_a_star_pathfinding(): void
    {
        $roadGraph = app(\App\Services\RoadGraphService::class);
        $this->assertGreaterThanOrEqual(20, $roadGraph->getNodeCount());
        $this->assertGreaterThanOrEqual(30, $roadGraph->getEdgeCount());

        // Test pathfinding between Plaza and Victoria Park Summit (Mount Cassamata)
        $route = $roadGraph->findRoute(17.5956, 120.6185, 17.5932, 120.6278);
        $this->assertNotNull($route);
        $this->assertEquals('local_graph', $route['source']);
        $this->assertFalse($route['is_fallback']);
        $this->assertNotEmpty($route['road_names']);
        $this->assertGreaterThan(500, $route['distance_meters']);
    }

    public function test_route_hud_displays_local_road_graph_indicator(): void
    {
        $hud = file_get_contents(resource_path('views/components/map/route-hud.blade.php'));
        $this->assertStringContainsString("routeSummary.source === 'local_graph'", $hud);
        $this->assertStringContainsString('(Local Road Graph)', $hud);
    }
}

