<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlacePhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class PlacePhotoViewerTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_place_detail_exposes_ordered_photos_with_cover_photo_priority(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->create();

        $photo1 = PlacePhoto::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'path' => 'photos/test_photo_1.jpg',
            'caption' => 'Side Entrance',
            'is_cover' => false,
            'status' => 'approved',
        ]);

        $photo2 = PlacePhoto::create([
            'place_id' => $place->id,
            'user_id' => $user->id,
            'path' => 'photos/test_cover.jpg',
            'caption' => 'Main Front View',
            'is_cover' => true,
            'status' => 'approved',
        ]);

        $response = $this->getJson("/api/places/{$place->id}");
        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('photos', $data);
        $this->assertCount(2, $data['photos']);

        $photos = collect($data['photos']);
        $cover = $photos->firstWhere('is_cover', true);
        $this->assertNotNull($cover);
        $this->assertSame('photos/test_cover.jpg', $cover['path']);
    }

    public function test_hero_photo_container_implements_touch_gestures_and_spring_motion(): void
    {
        $viewContent = file_get_contents(resource_path('views/components/map/place-detail.blade.php'));

        $this->assertStringContainsString('spatial-hero-photo', $viewContent);
        $this->assertStringContainsString('onHeroPhotoTouchStart', $viewContent);
        $this->assertStringContainsString('onHeroPhotoTouchMove', $viewContent);
        $this->assertStringContainsString('onHeroPhotoTouchEnd', $viewContent);
        $this->assertStringContainsString('photoIsSwiping', $viewContent);
        $this->assertStringContainsString('photoDragOffset', $viewContent);
    }

    public function test_lightbox_canvas_implements_spatial_hud_filmstrip_and_power_shortcuts(): void
    {
        $viewContent = file_get_contents(resource_path('views/components/map/place-detail.blade.php'));

        // Spatial canvas and styling tokens
        $this->assertStringContainsString('spatial-lightbox-canvas', $viewContent);
        $this->assertStringContainsString('spatial-lightbox-img', $viewContent);
        $this->assertStringContainsString('spatial-hud-pill', $viewContent);
        $this->assertStringContainsString('spatial-filmstrip-scroll', $viewContent);
        $this->assertStringContainsString('spatial-active-thumb', $viewContent);

        // Micro-interactions and gestures
        $this->assertStringContainsString('onLightboxKeyDown', $viewContent);
        $this->assertStringContainsString('onLightboxWheel', $viewContent);
        $this->assertStringContainsString('toggleLightboxZoom', $viewContent);
        $this->assertStringContainsString('startLightboxDrag', $viewContent);
        $this->assertStringContainsString('moveLightboxDrag', $viewContent);
        $this->assertStringContainsString('endLightboxDrag', $viewContent);

        // Accessibility & WCAG requirements
        $this->assertStringContainsString('role="dialog"', $viewContent);
        $this->assertStringContainsString('aria-modal="true"', $viewContent);
        $this->assertStringContainsString('aria-live="polite"', $viewContent);
        $this->assertStringContainsString('lightboxAnnouncement', $viewContent);
    }

    public function test_map_shell_provides_spatial_media_engine_methods(): void
    {
        $shellContent = file_get_contents(resource_path('js/map/map-shell.js'));

        $this->assertStringContainsString('toggleLightboxZoom', $shellContent);
        $this->assertStringContainsString('zoomInLightbox', $shellContent);
        $this->assertStringContainsString('zoomOutLightbox', $shellContent);
        $this->assertStringContainsString('resetLightboxTransform', $shellContent);
        $this->assertStringContainsString('startLightboxDrag', $shellContent);
        $this->assertStringContainsString('moveLightboxDrag', $shellContent);
        $this->assertStringContainsString('endLightboxDrag', $shellContent);
        $this->assertStringContainsString('onHeroPhotoTouchStart', $shellContent);
        $this->assertStringContainsString('onHeroPhotoTouchMove', $shellContent);
        $this->assertStringContainsString('onHeroPhotoTouchEnd', $shellContent);
        $this->assertStringContainsString('onLightboxKeyDown', $shellContent);
        $this->assertStringContainsString('goToPhoto', $shellContent);
        $this->assertStringContainsString('prefetchAdjacentPhotos', $shellContent);
    }

    public function test_predictive_preloader_module_implements_memory_caching(): void
    {
        $preloaderPath = resource_path('js/map/preloader.js');
        $this->assertFileExists($preloaderPath);

        $preloaderContent = file_get_contents($preloaderPath);
        $this->assertStringContainsString('prefetchAdjacentPhotos', $preloaderContent);
        $this->assertStringContainsString('preloadPhotoUrl', $preloaderContent);
        $this->assertStringContainsString('isPhotoPreloaded', $preloaderContent);
    }

    public function test_app_css_contains_media_engine_design_tokens(): void
    {
        $cssContent = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('--media-spring-bounce', $cssContent);
        $this->assertStringContainsString('--media-spring-duration', $cssContent);
        $this->assertStringContainsString('--media-hud-bg', $cssContent);
        $this->assertStringContainsString('--media-hud-border', $cssContent);
        $this->assertStringContainsString('--media-active-glow', $cssContent);
    }

    public function test_navigation_stacking_is_elevated_above_place_dock(): void
    {
        $appLayout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString('z-[60]', $appLayout);
    }

    public function test_map_shell_toggles_detail_when_clicked_again(): void
    {
        $shellContent = file_get_contents(resource_path('js/map/map-shell.js'));
        $this->assertStringContainsString('this.detailOpen && this.detailPlace && (this.detailPlace.id === id', $shellContent);
        $this->assertStringContainsString('this.closeDetail();', $shellContent);
    }

    public function test_photo_hover_overlay_removes_dark_background_boxes(): void
    {
        $viewContent = file_get_contents(resource_path('views/components/map/place-detail.blade.php'));
        $this->assertStringNotContainsString('hover:bg-black/20', $viewContent);
        $this->assertStringNotContainsString('hover:bg-black/10', $viewContent);
        $this->assertStringContainsString('group-hover/mid:opacity-100', $viewContent);
    }

    public function test_lightbox_tactile_navigation_buttons_are_isolated_and_elevated(): void
    {
        $viewContent = file_get_contents(resource_path('views/components/map/place-detail.blade.php'));
        $this->assertStringContainsString('z-40 flex items-center justify-between', $viewContent);
        $this->assertStringContainsString('@mousedown.stop', $viewContent);
        $this->assertStringContainsString('@touchstart.stop', $viewContent);
    }

    public function test_photo_modals_are_dark_mode_invariant(): void
    {
        $cssContent = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('html[data-theme="light"] .spatial-lightbox-canvas', $cssContent);
        $this->assertStringContainsString('html[data-theme="light"] .spatial-hud-pill', $cssContent);
        $this->assertStringContainsString('html[data-theme="light"] .spatial-hero-photo', $cssContent);
    }
}
