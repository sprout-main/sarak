<div
    x-show="aboutOpen"
    x-cloak
    class="dlg-backdrop"
    @click.self="closeAbout()"
    @keydown.escape.window="closeAbout()"
    @open-about-modal.window="openAbout()"
>
    <div class="dlg-card map-scroll max-w-xl" role="dialog" aria-modal="true" aria-label="About Sarak">
        <button
            type="button"
            class="icon-btn absolute right-4 top-4 z-10"
            @click="closeAbout()"
            aria-label="Close about modal"
        >
            <i class="ph ph-x"></i>
        </button>

        <div class="p-5 sm:p-6" x-data="{ tab: 'credits' }">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-400/10 text-emerald-400 border border-emerald-400/20">
                    <i class="ph ph-info text-xl"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-zinc-100">About Sarak</h2>
                    <p class="text-xs text-zinc-400">Civic map infrastructure for Bangued, Abra</p>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="flex items-center gap-1 border-b border-white/10 pb-3 mb-4">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors cursor-pointer"
                    :class="tab === 'credits' ? 'bg-emerald-400/15 text-emerald-400 border border-emerald-400/30' : 'text-zinc-400 hover:text-white hover:bg-white/5'"
                    @click="tab = 'credits'"
                >
                    <i class="ph ph-handshake mr-1 text-sm"></i> Credits & Sources
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors cursor-pointer"
                    :class="tab === 'disclaimer' ? 'bg-amber-400/15 text-amber-300 border border-amber-400/30' : 'text-zinc-400 hover:text-white hover:bg-white/5'"
                    @click="tab = 'disclaimer'"
                >
                    <i class="ph ph-shield-warning mr-1 text-sm"></i> Disclaimer
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors cursor-pointer"
                    :class="tab === 'privacy' ? 'bg-sky-400/15 text-sky-400 border border-sky-400/30' : 'text-zinc-400 hover:text-white hover:bg-white/5'"
                    @click="tab = 'privacy'"
                >
                    <i class="ph ph-lock-key mr-1 text-sm"></i> Data Privacy
                </button>
            </div>

            <!-- Credits Tab Content -->
            <div x-show="tab === 'credits'" class="space-y-3.5 text-xs leading-relaxed text-zinc-300">
                <div class="rounded-xl border border-white/10 bg-zinc-900/60 p-3.5">
                    <h4 class="font-semibold text-zinc-100 flex items-center gap-1.5 mb-1">
                        <i class="ph ph-globe-hemisphere-west text-emerald-400"></i> OpenStreetMap (OSM)
                    </h4>
                    <p class="text-zinc-400">
                        Map data &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer" class="text-emerald-400 underline hover:text-emerald-300">OpenStreetMap contributors</a>, licensed under the Open Database License (ODbL). Standard OSM raster tiles provided by the OpenStreetMap Foundation.
                    </p>
                </div>

                <div class="rounded-xl border border-white/10 bg-zinc-900/60 p-3.5">
                    <h4 class="font-semibold text-zinc-100 flex items-center gap-1.5 mb-1">
                        <i class="ph ph-map-trifold text-sky-400"></i> CARTO & Esri ArcGIS
                    </h4>
                    <p class="text-zinc-400">
                        Basemap styling tiles powered by CARTO Dark Matter and Esri World Imagery (Maxar, Earthstar Geographics, and GIS User Community).
                    </p>
                </div>

                <div class="rounded-xl border border-white/10 bg-zinc-900/60 p-3.5">
                    <h4 class="font-semibold text-zinc-100 flex items-center gap-1.5 mb-1">
                        <i class="ph ph-shapes text-amber-400"></i> Phosphor Icons
                    </h4>
                    <p class="text-zinc-400">
                        Interface iconography provided by Phosphor Icons under the MIT License.
                    </p>
                </div>

                <div class="rounded-xl border border-white/10 bg-zinc-900/60 p-3.5">
                    <h4 class="font-semibold text-zinc-100 flex items-center gap-1.5 mb-1">
                        <i class="ph ph-buildings text-violet-400"></i> Bangued Local Community & LGU Records
                    </h4>
                    <p class="text-zinc-400">
                        Civic boundaries, landmark datasets, and place coordinates maintained with the support of Abra community volunteers and public municipal references.
                    </p>
                </div>
            </div>

            <!-- Disclaimer Tab Content -->
            <div x-show="tab === 'disclaimer'" class="space-y-3.5 text-xs leading-relaxed text-zinc-300">
                <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-4">
                    <h4 class="font-semibold text-amber-300 flex items-center gap-1.5 mb-1.5 text-sm">
                        <i class="ph ph-warning text-base"></i> Operational Accuracy Notice
                    </h4>
                    <p class="text-zinc-300 mb-2">
                        Sarak is a community-powered geographic discovery platform. Business hours, contact numbers, amenities, and pin locations are submitted and updated by community contributors and may change without immediate notice.
                    </p>
                    <p class="text-zinc-400">
                        Always verify crucial emergency medical services, government services, or urgent travel plans with direct phone contact when possible.
                    </p>
                </div>

                <div class="rounded-xl border border-white/10 bg-zinc-900/60 p-3.5">
                    <h4 class="font-semibold text-zinc-100 flex items-center gap-1.5 mb-1">
                        <i class="ph ph-compass text-zinc-400"></i> Wayfinding Reference Only
                    </h4>
                    <p class="text-zinc-400">
                        Spatial boundaries and barangay delineations provided on this map are for informational and wayfinding reference only. They do not constitute official cadastral, legal boundary, or land title surveys.
                    </p>
                </div>
            </div>

            <!-- Privacy Tab Content -->
            <div x-show="tab === 'privacy'" class="space-y-3.5 text-xs leading-relaxed text-zinc-300">
                <div class="rounded-xl border border-sky-500/20 bg-sky-500/5 p-4">
                    <h4 class="font-semibold text-sky-400 flex items-center gap-1.5 mb-1.5 text-sm">
                        <i class="ph ph-shield-check text-base"></i> Client-Side GPS Only
                    </h4>
                    <p class="text-zinc-300 mb-2">
                        When you use the "Locate me" feature, geolocation queries are processed strictly in your device's web browser. Sarak does <strong>not</strong> track, store, or sell your movement history or real-time location.
                    </p>
                    <p class="text-zinc-400">
                        Coordinates are transmitted to the server only when you explicitly submit or edit a public place pin.
                    </p>
                </div>

                <div class="rounded-xl border border-white/10 bg-zinc-900/60 p-3.5">
                    <h4 class="font-semibold text-zinc-100 flex items-center gap-1.5 mb-1">
                        <i class="ph ph-user text-zinc-400"></i> Account Data & Moderation
                    </h4>
                    <p class="text-zinc-400">
                        Only your display name and public contributions (places added, edits, and karma score) are visible to other users. Email addresses and account credentials are secure and never exposed publicly.
                    </p>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between">
                <span class="mono text-[11px] text-zinc-500">Sarak GIS &middot; Bangued, Abra</span>
                <button
                    type="button"
                    class="btn btn-ghost !py-1.5 text-xs font-semibold"
                    @click="closeAbout()"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
