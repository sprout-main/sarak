<div class="relative" @click.outside="layersOpen = false" @keydown.escape.window="layersOpen = false">
    <button
        type="button"
        class="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-zinc-900/80 text-zinc-300 backdrop-blur transition-all duration-200 hover:text-white cursor-pointer"
        :class="layersOpen ? '!border-emerald-400 !text-emerald-400 ring-2 ring-emerald-400/20' : ''"
        @click="layersOpen = !layersOpen"
        :aria-expanded="layersOpen ? 'true' : 'false'"
        aria-label="Switch map layers">
        <i class="ph text-lg" :class="{
            'ph-map-trifold text-emerald-400': activeLayer === 'leaflet',
            'ph-compass text-sky-400': activeLayer === 'sarak',
            'ph-globe-hemisphere-west text-amber-400': activeLayer === 'satellite'
        }"></i>
    </button>

    <div
        x-show="layersOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-1 scale-95"
        class="absolute right-0 top-12 z-40 w-64 rounded-2xl border border-white/10 bg-zinc-950/95 p-2 shadow-2xl backdrop-blur-md">
        
        <div class="flex items-center justify-between px-2.5 py-1.5 border-b border-white/10 mb-1.5">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400">Map View</span>
            <span class="text-[10px] text-zinc-500 font-mono" x-text="activeLayer.toUpperCase()"></span>
        </div>

        <div class="space-y-1">
            <!-- Standard OpenStreetMap View (Default) -->
            <button
                type="button"
                @click="setLayer('leaflet'); layersOpen = false"
                class="flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-left transition-colors cursor-pointer"
                :class="activeLayer === 'leaflet' ? 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30' : 'text-zinc-300 hover:bg-white/5 hover:text-white'">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-400">
                        <i class="ph ph-map-trifold text-lg"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-semibold">Standard OpenStreetMap</span>
                            <span class="rounded bg-emerald-500/20 px-1 py-0.2 text-[9px] font-bold text-emerald-400">Default</span>
                        </div>
                        <div class="truncate text-[10px] text-zinc-400">Official OpenStreetMap tiles</div>
                    </div>
                </div>
                <i x-show="activeLayer === 'leaflet'" class="ph ph-check-circle text-base text-emerald-400 ml-1"></i>
            </button>

            <!-- SARAK Carto View -->
            <button
                type="button"
                @click="setLayer('sarak'); layersOpen = false"
                class="flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-left transition-colors cursor-pointer"
                :class="activeLayer === 'sarak' ? 'bg-sky-500/15 text-sky-400 ring-1 ring-sky-500/30' : 'text-zinc-300 hover:bg-white/5 hover:text-white'">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-500/10 text-sky-400">
                        <i class="ph ph-compass text-lg"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-semibold">SARAK</span>
                            <span class="rounded bg-sky-500/20 px-1 py-0.2 text-[9px] font-bold text-sky-400">Carto</span>
                        </div>
                        <div class="truncate text-[10px] text-zinc-400">Minimal vector raster</div>
                    </div>
                </div>
                <i x-show="activeLayer === 'sarak'" class="ph ph-check-circle text-base text-sky-400 ml-1"></i>
            </button>

            <!-- Satellite View -->
            <button
                type="button"
                @click="setLayer('satellite'); layersOpen = false"
                class="flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-left transition-colors cursor-pointer"
                :class="activeLayer === 'satellite' ? 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30' : 'text-zinc-300 hover:bg-white/5 hover:text-white'">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-400">
                        <i class="ph ph-globe-hemisphere-west text-lg"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-semibold">Satellite</span>
                            <span class="rounded bg-amber-500/20 px-1 py-0.2 text-[9px] font-bold text-amber-400">Aerial</span>
                        </div>
                        <div class="truncate text-[10px] text-zinc-400">High-res aerial imagery</div>
                    </div>
                </div>
                <i x-show="activeLayer === 'satellite'" class="ph ph-check-circle text-base text-amber-400 ml-1"></i>
            </button>
        </div>
    </div>
</div>
