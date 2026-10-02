<?php

namespace Tests\Feature;

use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class PlaceThemeAndZoomFixesTest extends TestCase
{
    use CoreCatalog;

    public function test_category_chips_blend_with_ghost_bg_not_raw_transparent(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        // Verify chip.on blends with ghost bg
        $this->assertStringContainsString('color-mix(in srgb, var(--cat-hue) 22%, var(--atlas-ghost-bg))', $css);
        $this->assertStringNotContainsString('.chip.on { color: var(--atlas-strong-text); border-color: var(--cat-hue); background: color-mix(in srgb, var(--cat-hue) 16%, transparent); }', $css);

        // Verify chip bar dock pill in Blade
        $blade = file_get_contents(resource_path('views/components/map/category-chip.blade.php'));
        $this->assertStringContainsString('glass-strong', $blade);
        $this->assertStringContainsString('rounded-full', $blade);
    }

    public function test_marker_glow_is_removed_on_sarak_dark_and_satellite_views(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        // Verify scoped removal of marker glow on SARAK (dark)
        $this->assertStringContainsString('[data-map-view="sarak"] .map-place-logo', $css);
        $this->assertStringContainsString('filter: none;', $css);

        // Verify Satellite view inherits Leaflet category markers (no filter: none suppression)
        $this->assertStringNotContainsString('[data-map-view="satellite"] .map-place-logo', $css);

        // Ensure default drop-shadow is preserved for Leaflet and Satellite views
        $this->assertStringContainsString('filter: drop-shadow(0 0 1.5px #ffffff)', $css);
    }

    public function test_map_shell_maintains_current_zoom_if_already_zoomed_in(): void
    {
        $shell = file_get_contents(resource_path('js/map/map-shell.js'));

        // Halfway zoom and current zoom retention
        $this->assertStringContainsString('(minZoom + maxZoom) / 2', $shell);
        $this->assertStringContainsString('Math.max(currentZoom, halfwayZoom)', $shell);
        $this->assertStringContainsString('Math.min(maxZoom, Math.max(currentZoom, halfwayZoom))', $shell);
    }

    public function test_spatial_photo_modals_maintain_dark_invariance_in_all_themes(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        // Verify hero photo and lightbox controls are forced white in light theme
        $this->assertStringContainsString('.spatial-hero-photo span', $css);
        $this->assertStringContainsString('html[data-theme="light"] .spatial-hero-photo span', $css);
        $this->assertStringContainsString('html[data-theme="light"] .spatial-lightbox-canvas .text-white', $css);
        $this->assertStringContainsString('color: #ffffff !important;', $css);

        // Verify no prefers-reduced-motion exists
        $this->assertStringNotContainsString('prefers-reduced-motion', $css);
    }

    public function test_hero_photo_buttons_and_lightbox_exit_button_are_hardened(): void
    {
        $blade = file_get_contents(resource_path('views/components/map/place-detail.blade.php'));

        // Hero photo buttons have explicit white text and high-contrast translucent dark backgrounds
        $this->assertStringContainsString('bg-black/75 text-white', $blade);
        $this->assertStringContainsString('text-white">Expand</span>', $blade);

        // Lightbox close button has shrink-0 and explicit high contrast classes
        $this->assertStringContainsString('shrink-0 items-center justify-center rounded-full bg-zinc-900/95 text-white', $blade);
        $this->assertStringContainsString('closePhotoLightbox()', $blade);
        $this->assertStringContainsString('ph-bold ph-x', $blade);
    }

    public function test_lightbox_is_teleported_above_navbar_and_controls_and_titles_are_isolated(): void
    {
        $blade = file_get_contents(resource_path('views/components/map/place-detail.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));
        $shell = file_get_contents(resource_path('js/map/map-shell.js'));
        $detail = file_get_contents(resource_path('js/map/detail.js'));

        // Blade: Lightbox canvas is teleported to body so it escapes .place-dock stacking context
        $this->assertStringContainsString('<template x-teleport="body">', $blade);
        $this->assertStringContainsString('z-[99999]', $blade);
        $this->assertStringContainsString('spatial-lightbox-canvas', $blade);

        // Blade: Title pill has proper truncation and tooltip title attribute
        $this->assertStringContainsString('truncate block min-w-0 flex-1', $blade);
        $this->assertStringContainsString(':title="detailPlace ? detailPlace.name : \'\'"', $blade);
        $this->assertStringContainsString('shrink-0 ml-auto', $blade);

        // CSS: Nav is hidden and overflow hidden when lightbox is open
        $this->assertStringContainsString('body.has-lightbox-open nav', $css);
        $this->assertStringContainsString('display: none !important;', $css);
        $this->assertStringContainsString('z-index: 99999 !important;', $css);

        // JS: has-lightbox-open class added/removed on body
        $this->assertStringContainsString("document.body.classList.add('has-lightbox-open')", $shell);
        $this->assertStringContainsString("document.body.classList.remove('has-lightbox-open')", $shell);
        $this->assertStringContainsString("document.body.classList.remove('has-lightbox-open')", $detail);
    }

    public function test_empty_state_alert_modal_is_removed_from_map(): void
    {
        $map = file_get_contents(resource_path('views/map.blade.php'));
        $emptyState = file_get_contents(resource_path('views/components/map/empty-state.blade.php'));

        // Empty state component removed from map blade view
        $this->assertStringNotContainsString('<x-map.empty-state', $map);

        // Component itself unconditionally set to hidden
        $this->assertStringContainsString('x-show="false"', $emptyState);
    }

    public function test_clicking_map_unclicks_place_and_unshows_profile_modal(): void
    {
        $shell = file_get_contents(resource_path('js/map/map-shell.js'));
        $markers = file_get_contents(resource_path('js/map/markers.js'));

        // Map shell registers click listener to unclick place and close detail modal
        $this->assertStringContainsString("this._map.on('click', (e) =>", $shell);
        $this->assertStringContainsString('if (this.detailOpen) {', $shell);
        $this->assertStringContainsString('this.closeDetail();', $shell);

        // Guarded against clicks on markers, controls, and the place dock card
        $this->assertStringContainsString("target.closest('.map-place-logo-container')", $shell);
        $this->assertStringContainsString("target.closest('.place-dock')", $shell);
        $this->assertStringContainsString("target.closest('.leaflet-control')", $shell);

        // Markers stop propagation so clicking a marker does not trigger map dismissal
        $this->assertStringContainsString('L.DomEvent.stopPropagation(e)', $markers);
    }
}
