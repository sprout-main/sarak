<?php

namespace Tests\Feature;

use App\Models\Place;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class PlaceClickFocusHalfwayTest extends TestCase
{
    use CoreCatalog;

    public function test_api_places_do_not_expose_logo_url(): void
    {
        $response = $this->getJson('/api/places');
        $response->assertOk();

        $data = $response->json('data.0');
        if ($data) {
            $this->assertArrayNotHasKey('logo_url', $data);
            $this->assertArrayNotHasKey('logo_path', $data);
        }
    }

    public function test_markers_js_renders_clean_category_icon_without_brand_logos(): void
    {
        $markers = file_get_contents(resource_path('js/map/markers.js'));

        $this->assertStringNotContainsString('map-place-brand-logo', $markers);
        $this->assertStringNotContainsString('has-brand-logo', $markers);
        $this->assertStringNotContainsString('<img', $markers);
        $this->assertStringContainsString("marker.on('click'", $markers);
        $this->assertStringNotContainsString('bindPopup', $markers);
    }

    public function test_map_shell_focuses_and_zooms_halfway(): void
    {
        $shell = file_get_contents(resource_path('js/map/map-shell.js'));

        // Halfway zoom calculation
        $this->assertStringContainsString('(minZoom + maxZoom) / 2', $shell);
        $this->assertStringNotContainsString('const zoom = this._map.getMaxZoom();', $shell);

        // Click focus and repeat-click toggle
        $this->assertStringContainsString('onOpen: (id) => this.openDetail(id)', $shell);
        $this->assertStringContainsString('this.closeDetail()', $shell);
        $this->assertStringContainsString('this.focusPlace(place)', $shell);

        // No max-zoom tracking for brand logos
        $this->assertStringNotContainsString('updateMaxZoomState', $shell);
    }

    public function test_app_css_contains_no_brand_logo_or_reduced_motion(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringNotContainsString('map-place-brand-logo', $css);
        $this->assertStringNotContainsString('.at-max-zoom', $css);
        $this->assertStringNotContainsString('prefers-reduced-motion', $css);
    }
}
