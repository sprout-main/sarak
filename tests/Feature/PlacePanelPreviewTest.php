<?php

namespace Tests\Feature;

use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class PlacePanelPreviewTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_place_payloads_no_longer_expose_karma_score(): void
    {
        $place = Place::factory()->withHours()->create();

        $list = $this->getJson('/api/places')->assertOk()->json('data.0');
        $this->assertIsArray($list);
        $this->assertArrayNotHasKey('karma_score', $list);

        $detail = $this->getJson("/api/places/{$place->id}")->assertOk()->json('data');
        $this->assertArrayNotHasKey('karma_score', $detail);

        $this->assertArrayHasKey('is_claimed', $detail);
        $this->assertCount(7, $detail['hours']);
    }

    public function test_approving_a_submitted_place_awards_user_karma_but_not_place_karma(): void
    {
        $user = $this->residentUser();
        $admin = $this->adminUser();
        $place = Place::factory()->create([
            'status' => 'pending',
            'submitted_by' => $user->id,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/places/{$place->id}", [
                'status' => 'approved',
            ])
            ->assertOk();

        $place->refresh();
        $user->refresh();

        $this->assertSame(0, (int) $place->getRawOriginal('karma_score'));
        $this->assertSame(20, $user->karma_points);
    }

    public function test_karma_service_no_longer_awards_place_karma(): void
    {
        $service = app(\App\Services\KarmaService::class);

        $this->assertFalse(method_exists($service, 'awardPlace'));
    }

    public function test_markers_no_longer_bind_a_leaflet_popup(): void
    {
        $markers = file_get_contents(resource_path('js/map/markers.js'));

        $this->assertStringNotContainsString('bindPopup', $markers);
        $this->assertStringNotContainsString('popupopen', $markers);
        $this->assertStringNotContainsString('js-view-profile', $markers);
        $this->assertStringNotContainsString('popupHtml', $markers);
        $this->assertStringContainsString("marker.on('click'", $markers);
    }

    public function test_map_shell_opens_the_panel_instead_of_a_popup(): void
    {
        $shell = file_get_contents(resource_path('js/map/map-shell.js'));

        $this->assertStringNotContainsString('openPopup', $shell);
        $this->assertStringContainsString('onOpen: (id) => this.openDetail(id)', $shell);
        $this->assertStringContainsString('readPlaceId', $shell);
    }

    public function test_panel_renders_the_hours_dropdown_and_full_profile(): void
    {
        $panel = file_get_contents(resource_path('views/components/map/place-detail.blade.php'));

        $this->assertStringContainsString('place-dock', $panel);
        $this->assertStringContainsString('hours-toggle', $panel);
        $this->assertStringContainsString('detailHeadline.state', $panel);
        $this->assertStringContainsString('detailHeadline.detail', $panel);
        $this->assertStringContainsString('toggleHours()', $panel);
        $this->assertStringContainsString('daysTableFor(detailPlace.hours)', $panel);
        $this->assertStringContainsString('Directions', $panel);
        $this->assertStringNotContainsString('dlg-backdrop', $panel);
        $this->assertStringNotContainsString('View profile', $panel);
        $this->assertStringNotContainsString('karma', strtolower($panel));
    }

    public function test_hours_headline_module_defines_the_open_closed_wording(): void
    {
        $opennow = file_get_contents(resource_path('js/map/opennow.js'));

        $this->assertStringContainsString('export function hoursHeadline', $opennow);
        $this->assertStringContainsString('Closes at', $opennow);
        $this->assertStringContainsString('Opens at', $opennow);
        $this->assertStringContainsString('24 hours', $opennow);
        $this->assertStringContainsString('Hours not provided', $opennow);
        $this->assertStringContainsString('export function nextOpenRow', $opennow);
        $this->assertStringContainsString("timeZone: 'Asia/Manila'", $opennow);
    }

    public function test_map_views_are_english_only(): void
    {
        $banned = ['Wala pa tayo', 'Walang nahanap', 'Tulungan mo', 'Walang photo'];

        $views = [
            resource_path('views/components/map/empty-state.blade.php'),
            resource_path('views/components/map/searchbox.blade.php'),
            resource_path('views/components/map/place-detail.blade.php'),
        ];

        foreach ($views as $view) {
            $contents = file_get_contents($view);
            foreach ($banned as $phrase) {
                $this->assertStringNotContainsString($phrase, $contents, "{$view} still contains Tagalog copy: {$phrase}");
            }
        }

        $emptyState = file_get_contents(resource_path('views/components/map/empty-state.blade.php'));
        $this->assertStringContainsString('Nothing here yet', $emptyState);
        $this->assertStringContainsString('Help us map it', $emptyState);
    }

    public function test_map_page_composes_the_place_panel(): void
    {
        $map = file_get_contents(resource_path('views/map.blade.php'));

        $this->assertStringContainsString('<x-map.place-detail />', $map);
    }
}
