<div
    x-show="closestDrawerOpen"
    x-cloak
    class="place-dock"
    @keydown.escape.window="closeClosestDrawer()"
>
    <div
        class="place-dock-scrim md:hidden"
        x-show="closestDrawerOpen"
        @click="closeClosestDrawer()"
    ></div>

    <div
        class="place-dock-card map-scroll"
        role="region"
        aria-label="Closest establishments"
    >
        <button
            type="button"
            class="icon-btn place-dock-close"
            @click="closeClosestDrawer()"
            aria-label="Close closest establishments drawer"
        >
            <i class="ph ph-x"></i>
        </button>

        <div class="p-5 sm:p-6">
            <div class="flex items-center gap-3 mb-4 pr-8">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-400/10 text-emerald-400 border border-emerald-400/20">
                    <i class="ph ph-compass text-xl"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-zinc-100">Closest Establishments</h3>
                    <p class="text-xs text-zinc-400">
                        <span x-text="closestPlaces.length"></span> places near your location
                    </p>
                </div>
            </div>

            <template x-if="closestLoading">
                <div class="space-y-3 py-2">
                    <div class="h-16 w-full animate-pulse rounded-xl bg-white/5"></div>
                    <div class="h-16 w-full animate-pulse rounded-xl bg-white/5"></div>
                    <div class="h-16 w-full animate-pulse rounded-xl bg-white/5"></div>
                </div>
            </template>

            <template x-if="!closestLoading && closestPlaces.length === 0">
                <div class="rounded-xl border border-white/10 bg-zinc-900/60 p-6 text-center my-4">
                    <i class="ph ph-map-pin-line text-3xl text-zinc-500 mb-2 block"></i>
                    <p class="text-sm font-medium text-zinc-300">No establishments found nearby</p>
                    <p class="text-xs text-zinc-500 mt-1">Try expanding your category filter or clearing location.</p>
                </div>
            </template>

            <div x-show="!closestLoading && closestPlaces.length > 0" class="space-y-2 mt-2">
                <template x-for="p in closestPlaces" :key="p.id">
                    <button
                        type="button"
                        class="w-full text-left rounded-xl border border-white/10 bg-zinc-900/60 p-3.5 transition-all hover:bg-white/5 hover:border-emerald-400/30 flex items-start gap-3 group cursor-pointer"
                        @click="focusPlace(p); closeClosestDrawer();"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-white/10 mt-0.5"
                            :style="'background:' + (p.category ? p.category.color : 'var(--cat-hue)') + '22; color:' + (p.category ? p.category.color : 'var(--cat-hue)')"
                        >
                            <i class="ph-fill text-base" :class="catIcon(p.category ? (p.category.icon_key || p.category.slug) : '')"></i>
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-zinc-100 truncate group-hover:text-emerald-300 transition-colors" x-text="p.name"></span>
                                <span class="mono shrink-0 text-xs font-medium text-emerald-400 bg-emerald-400/10 px-2 py-0.5 rounded-full border border-emerald-400/20" x-text="fd(p.distance_m) || 'Near'"></span>
                            </div>

                            <div class="mt-1 flex items-center gap-2 text-xs text-zinc-400 flex-wrap">
                                <span class="truncate" x-text="p.address || (p.barangay ? p.barangay.name : (p.category ? p.category.label : 'Bangued'))"></span>
                            </div>

                            <div class="mt-2 flex items-center gap-2">
                                <template x-if="p.is_open !== undefined && p.is_open !== null">
                                    <span
                                        class="badge text-[10px] font-medium"
                                        :class="p.is_open ? 'badge-emerald' : 'badge-zinc'"
                                        x-text="p.is_open ? 'Open now' : 'Closed'"
                                    ></span>
                                </template>
                                <template x-if="p.category">
                                    <span class="text-[11px] text-zinc-500 truncate" x-text="p.category.label"></span>
                                </template>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
        </div>
    </div>
</div>
