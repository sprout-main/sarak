<x-app-layout>
    <div
        x-data="mapComposer()"
        :data-map-view="activeLayer"
        x-cloak
        class="relative min-h-[calc(100dvh-3.5rem)] overflow-hidden bg-zinc-950"
    >
        <script>
            window.SarakUser = @json(auth()->check());
            window.AtlasUser = window.SarakUser;
            window.SarakUserAvatar = @json(auth()->user()?->avatar_url);
            window.SarakUserName = @json(auth()->user()?->name);
            window.SarakIsAdmin = @json(auth()->user()?->isAdmin() ?? false);
            window.SarakIsHeadAdmin = @json(auth()->user()?->isHeadAdmin() ?? false);
        </script>

        <div class="absolute inset-0 z-0">
            <div x-ref="map" class="absolute inset-0"></div>
        </div>

        <!-- Dropped Pin Floating Control Banner -->
        <div
            x-show="pickingLocation"
            x-cloak
            class="absolute top-4 left-1/2 z-40 -translate-x-1/2 w-[92vw] max-w-md transition-all duration-200"
        >
            <div class="glass-strong panel-12 p-4 tint-shadow border border-emerald-400/40 shadow-2xl flex flex-col gap-2.5">
                <div class="flex items-center justify-between">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-400">
                        <i class="ph ph-map-pin text-sm"></i> Pin Location Mode
                    </span>
                    <span class="font-mono text-[11px] text-zinc-300" x-text="formLat && formLng ? formLat + ', ' + formLng : 'Click on map'"></span>
                </div>
                <p class="text-xs text-zinc-400">
                    Click anywhere on the map or drag the emerald pin to position the place.
                </p>
                <div class="flex items-center gap-2 pt-1">
                    <button
                        type="button"
                        class="btn btn-primary flex-1 !py-2 text-xs font-semibold !bg-emerald-400 !text-zinc-950 hover:!bg-emerald-300"
                        @click="confirmPickedLocation()"
                        :disabled="!formLat || !formLng"
                    >
                        <i class="ph ph-check text-sm"></i> Confirm Location
                    </button>
                    <button
                        type="button"
                        class="btn btn-ghost !py-2 text-xs"
                        @click="cancelPickingLocation()"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <!-- Admin Move Pin Floating Control Banner -->
        <div
            x-show="adminMovingPlace"
            x-cloak
            class="absolute top-4 left-1/2 z-40 -translate-x-1/2 w-[92vw] max-w-md transition-all duration-200"
        >
            <div class="glass-strong panel-12 p-4 tint-shadow border border-sky-400/50 shadow-2xl flex flex-col gap-2.5">
                <div class="flex items-center justify-between">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-sky-400">
                        <i class="ph ph-arrows-out-cardinal text-sm"></i> Repositioning Pin
                    </span>
                    <span class="font-mono text-[11px] text-zinc-300" x-text="adminMoveLat && adminMoveLng ? Number(adminMoveLat).toFixed(5) + ', ' + Number(adminMoveLng).toFixed(5) : ''"></span>
                </div>
                <p class="text-xs text-zinc-400">
                    Drag the pin on the map to update the location for <strong class="text-zinc-200" x-text="adminMovingPlace ? adminMovingPlace.name : ''"></strong>.
                </p>
                <div class="flex items-center gap-2 pt-1">
                    <button
                        type="button"
                        class="btn btn-primary flex-1 !py-2 text-xs font-semibold !bg-sky-400 !text-zinc-950 hover:!bg-sky-300"
                        @click="adminSaveMovedLocation()"
                        :disabled="adminBusy"
                    >
                        <i class="ph ph-check text-sm"></i> Save Location
                    </button>
                    <button
                        type="button"
                        class="btn btn-ghost !py-2 text-xs"
                        @click="adminCancelMove()"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>


        <div
            x-show="panelOpen"
            x-cloak
            class="absolute inset-0 z-20 md:hidden"
            style="background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(2px);"
            @click="panelOpen = false"
        ></div>

        <button
            type="button"
            class="absolute left-3 top-3 z-30 flex !h-11 !w-11 items-center justify-center rounded-full border border-white/10 bg-zinc-900/80 text-zinc-300 backdrop-blur transition-all duration-200 hover:text-white md:left-5 md:top-5"
            :class="panelOpen ? 'pointer-events-none opacity-0' : 'opacity-100'"
            @click="panelOpen = true"
            aria-label="Open layers panel">
            <i class="ph ph-stack text-lg"></i>
        </button>

        <div
            class="absolute left-3 right-3 bottom-[5.5rem] z-30 transition-transform duration-300 ease-out md:left-5 md:right-auto md:top-20 md:bottom-auto md:w-80"
            :class="panelOpen ? 'translate-x-0 translate-y-0' : 'translate-y-[135%] md:translate-y-0 md:-translate-x-[135%]'">
            <div class="glass-strong map-scroll panel-12 tint-shadow max-h-[52dvh] overflow-y-auto md:max-h-[calc(100dvh-9rem)]">
                <x-map.legend />
                <div class="px-4 pb-4">
                    <x-map.filters />
                </div>
            </div>
        </div>

        <x-map.searchbox />

        <div class="absolute right-3 top-3 z-30 hidden items-center gap-2 sm:flex">
            <x-map.stat-pill label="Total places" icon="map-pin" bind="totalApproved" />
            <x-map.stat-pill label="In view" icon="crosshair" bind="places.length" />
            <x-map.stat-pill label="New pins" icon="pulse" bind="newIds.length" accented />
        </div>

        <div class="absolute right-3 top-3 z-30 flex items-center gap-2 sm:hidden">
            <x-map.stat-pill icon="map-pin" bind="totalApproved" />
        </div>

        <!-- Bottom Floating Dock: Status Bars Stacked Directly On Top of Category Selectors (Zero Overlap / Never Layered) -->
        <div class="pointer-events-none absolute bottom-4 left-1/2 z-30 flex -translate-x-1/2 flex-col items-center gap-2 max-w-[94vw] sm:max-w-[85vw] md:max-w-[75vw]">
            <!-- Dynamic Status Bars Stack: Toast announcements and Loading Pins indicator -->
            <div class="flex flex-col items-center gap-2 pointer-events-auto">
                <!-- Toast Status Notification Pill -->
                <div
                    x-show="toastMsg"
                    x-cloak
                    class="glass-strong tint-shadow flex items-center gap-2 rounded-full border border-white/10 px-4 py-2 shadow-2xl backdrop-blur-md"
                    role="status">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]"></span>
                    <span class="mono text-xs font-medium text-zinc-200" x-text="toastMsg"></span>
                </div>

                <!-- Loading Pins Pill Indicator -->
                <div
                    x-show="loading && ready"
                    x-cloak
                    class="glass-strong tint-shadow flex items-center gap-2 rounded-full border border-white/10 px-4 py-2 shadow-xl backdrop-blur-md">
                    <span class="h-2 w-2 rounded-full bg-[#4ade80] [animation:atlas-pulse_1s_ease-in-out_infinite]"></span>
                    <span class="mono text-xs font-medium text-zinc-300">Loading pins</span>
                </div>
            </div>

            <!-- Category Selectors (Always Grounded at the Bottom) -->
            <div class="pointer-events-auto max-w-full">
                <x-map.category-chip />
            </div>
        </div>

        <!-- Closest Places Floating Trigger Pill -->
        <button
            type="button"
            x-show="coords && closestPlaces.length > 0 && !closestDrawerOpen"
            x-cloak
            @click="openClosestDrawer()"
            class="fixed right-4 bottom-24 z-30 flex items-center gap-2 rounded-full border border-emerald-400/30 bg-zinc-900/90 px-3.5 py-2 text-xs font-medium text-emerald-400 shadow-xl backdrop-blur transition-all hover:bg-zinc-800 hover:border-emerald-400 cursor-pointer"
            aria-label="View closest establishments"
        >
            <i class="ph ph-compass text-base text-emerald-400"></i>
            <span>Closest places</span>
            <span class="mono rounded-full bg-emerald-400/20 px-1.5 py-0.5 text-[10px] font-bold text-emerald-300" x-text="closestPlaces.length"></span>
        </button>

        <x-map.fab />

        <x-map.route-hud />
        <x-map.place-detail />
        <x-map.closest-establishments-dock />
        <x-map.submission-form />
        <x-map.contributors-modal />
        <x-map.about-modal />
        <x-map.report-modal />

        @production
            <script>
                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', () => {
                        navigator.serviceWorker.register('/sw.js').catch(() => {});
                    });
                }
            </script>
        @endproduction
    </div>
</x-app-layout>