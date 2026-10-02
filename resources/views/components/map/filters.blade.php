<div class="mt-2 space-y-3 border-t border-white/10 pt-3">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="ph ph-funnel text-base text-zinc-400"></i>
            <span class="text-sm font-semibold text-zinc-100">Filters</span>
        </div>
        <button
            type="button"
            class="text-xs font-medium text-zinc-400 transition-colors hover:text-white"
            @click="clearFilters()"
            x-show="filterActiveCount() > 0">
            Clear all <span class="mono ml-1 text-emerald-400" x-text="filterActiveCount()"></span>
        </button>
    </div>

    <div>
        <label class="field-label">Map View</label>
        <div class="grid grid-cols-3 gap-1.5 pt-1">
            <button
                type="button"
                @click="setLayer('leaflet')"
                class="flex flex-col items-center justify-center gap-1 rounded-xl p-2 text-center text-xs transition-colors border cursor-pointer"
                :class="activeLayer === 'leaflet' ? 'border-emerald-400 bg-emerald-500/15 text-emerald-400 font-semibold ring-1 ring-emerald-500/30' : 'border-white/10 bg-white/5 text-zinc-300 hover:bg-white/10 hover:text-white'">
                <i class="ph ph-map-trifold text-base"></i>
                <span class="text-[11px]">Leaflet</span>
            </button>
            <button
                type="button"
                @click="setLayer('sarak')"
                class="flex flex-col items-center justify-center gap-1 rounded-xl p-2 text-center text-xs transition-colors border cursor-pointer"
                :class="activeLayer === 'sarak' ? 'border-sky-400 bg-sky-500/15 text-sky-400 font-semibold ring-1 ring-sky-500/30' : 'border-white/10 bg-white/5 text-zinc-300 hover:bg-white/10 hover:text-white'">
                <i class="ph ph-compass text-base"></i>
                <span class="text-[11px]">SARAK</span>
            </button>
            <button
                type="button"
                @click="setLayer('satellite')"
                class="flex flex-col items-center justify-center gap-1 rounded-xl p-2 text-center text-xs transition-colors border cursor-pointer"
                :class="activeLayer === 'satellite' ? 'border-amber-400 bg-amber-500/15 text-amber-400 font-semibold ring-1 ring-amber-500/30' : 'border-white/10 bg-white/5 text-zinc-300 hover:bg-white/10 hover:text-white'">
                <i class="ph ph-globe-hemisphere-west text-base"></i>
                <span class="text-[11px]">Satellite</span>
            </button>
        </div>
    </div>


    <div>
        <label class="field-label" for="sarak-hours">Hours</label>
        <select class="select-8" id="sarak-hours" x-model="hours" @change="setHours(hours)">
            <option value="all">All hours</option>
            <option value="open_now">Open now</option>
            <option value="open_24h">Open 24 hours</option>
            <option value="unknown">Hours unknown</option>
        </select>
    </div>


    <div class="flex items-center gap-2">
        <button
            type="button"
            class="btn btn-ghost flex-1 !py-2 text-xs"
            @click="nearMe()"
            :disabled="locating"
            x-show="!coords">
            <i class="ph ph-crosshair text-sm"></i>
            <span x-text="locating ? 'Locating...' : 'Locate me'"></span>
        </button>
        <button
            type="button"
            class="btn btn-ghost flex-1 !py-2 text-xs"
            @click="clearLocation()"
            x-show="coords">
            <i class="ph ph-x text-sm"></i>
            Clear location
        </button>
    </div>

    <template x-if="window.SarakIsAdmin">
        <div class="pt-2 border-t border-white/10">
            <div class="flex items-center justify-between py-1">
                <div class="flex items-center gap-2">
                    <i class="ph ph-clock-counter-clockwise text-amber-400 text-sm"></i>
                    <span class="text-xs font-semibold text-amber-400">Pending Reviews</span>
                </div>
                <button
                    type="button"
                    @click="togglePendingReviews()"
                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                    :class="showPendingReviews ? 'bg-amber-500' : 'bg-zinc-700'"
                    role="switch"
                    :aria-checked="showPendingReviews"
                >
                    <span
                        class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
                        :class="showPendingReviews ? 'translate-x-4' : 'translate-x-0'"
                    ></span>
                </button>
            </div>
            <p class="text-[10px] text-zinc-400">Show places pending moderation as amber pins</p>
        </div>
    </template>

    <div class="pt-2 border-t border-white/10 flex items-center justify-between text-xs text-zinc-400">
        <button
            type="button"
            @click="openAbout()"
            class="inline-flex items-center gap-1.5 hover:text-white transition-colors cursor-pointer py-1"
        >
            <i class="ph ph-info text-sm text-emerald-400"></i>
            <span>About Sarak & Disclaimers</span>
        </button>
    </div>
</div>