{{-- Staged Relocated Pins Side Drawer for Super Admin --}}
<div
    x-show="massDragActive"
    x-cloak
    class="pointer-events-none fixed inset-0 z-40 overflow-hidden"
>
    <!-- Collapsed Floating Indicator (when drawer is minimized) -->
    <div
        x-show="!draggedPinsDrawerOpen"
        x-cloak
        class="pointer-events-auto absolute top-20 right-4 z-40"
    >
        <button
            type="button"
            @click="draggedPinsDrawerOpen = true"
            class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-full glass-strong border border-emerald-500/40 text-emerald-300 shadow-xl hover:border-emerald-400 hover:text-white transition-all text-xs font-semibold backdrop-blur-md"
        >
            <i class="ph ph-arrows-out-cardinal text-emerald-400 text-sm"></i>
            <span>Relocated Pins</span>
            <span
                class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[10px] font-bold bg-emerald-400 text-zinc-950"
                x-text="draggedPins.length"
            ></span>
            <i class="ph ph-caret-left text-zinc-400 text-xs"></i>
        </button>
    </div>

    <!-- Slide-over Side Drawer -->
    <aside
        x-show="draggedPinsDrawerOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-x-8"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 translate-x-8"
        class="pointer-events-auto absolute right-3 top-16 bottom-20 md:bottom-6 z-40 w-[calc(100vw-1.5rem)] sm:w-96 flex flex-col rounded-2xl glass-strong border border-emerald-500/30 tint-shadow shadow-2xl overflow-hidden"
    >
        <!-- Header -->
        <div class="px-4 py-3.5 border-b border-white/10 bg-zinc-950/60 flex items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shrink-0">
                    <i class="ph ph-arrows-out-cardinal text-base"></i>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-100">Relocated Pins</h2>
                        <span
                            class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[10px] font-extrabold bg-emerald-400 text-zinc-950"
                            x-text="draggedPins.length"
                        ></span>
                    </div>
                    <p class="text-[11px] text-zinc-400 truncate">Staged moves ready to save</p>
                </div>
            </div>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="btn btn-ghost !p-1.5 text-zinc-400 hover:text-white"
                    @click="draggedPinsDrawerOpen = false"
                    title="Minimize drawer"
                    aria-label="Minimize drawer"
                >
                    <i class="ph ph-minus text-sm"></i>
                </button>
                <button
                    type="button"
                    class="btn btn-ghost !p-1.5 text-zinc-400 hover:text-white"
                    @click="toggleMassDrag(false)"
                    title="Exit Mass Drag Mode"
                    aria-label="Exit Mass Drag Mode"
                >
                    <i class="ph ph-x text-sm"></i>
                </button>
            </div>
        </div>

        <!-- Banner / Hint -->
        <div class="px-3.5 py-2 bg-emerald-500/10 border-b border-emerald-500/20 text-[11px] text-emerald-300/90 flex items-center gap-2 shrink-0">
            <i class="ph ph-info text-sm text-emerald-400 shrink-0"></i>
            <span>Drag any pin to move. Green tethers show origin positions.</span>
        </div>

        <!-- Empty State -->
        <div
            x-show="draggedPins.length === 0"
            class="flex-1 flex flex-col items-center justify-center p-6 text-center text-zinc-400 space-y-3"
        >
            <div class="w-12 h-12 rounded-full bg-zinc-900 border border-white/10 flex items-center justify-center text-zinc-500">
                <i class="ph ph-map-pin text-xl"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-zinc-200">No pins moved yet</p>
                <p class="text-[11px] text-zinc-400 mt-1 max-w-[240px]">
                    Click and drag any place pin on the map to relocate it. Staged changes will appear here.
                </p>
            </div>
            <div class="pt-2">
                <button
                    type="button"
                    class="btn btn-ghost btn-sm text-xs text-zinc-400 hover:text-white"
                    @click="toggleMassDrag(false)"
                >
                    Exit Mass Drag Mode
                </button>
            </div>
        </div>

        <!-- Staged Pins Cards List -->
        <div
            x-show="draggedPins.length > 0"
            class="flex-1 overflow-y-auto p-3 space-y-2.5 map-scroll"
        >
            <template x-for="p in draggedPins" :key="p.placeId">
                <div class="group relative rounded-xl border border-white/10 bg-zinc-900/70 hover:border-emerald-500/40 p-3 transition-colors shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-start gap-2.5 min-w-0">
                            <div class="mt-0.5 w-7 h-7 rounded-lg bg-zinc-800 border border-white/10 flex items-center justify-center text-emerald-400 shrink-0 text-sm">
                                <i :class="getCategoryIcon(p.category?.icon_key || p.category?.slug || 'grocery')"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <h3 class="text-xs font-semibold text-zinc-100 truncate" x-text="p.name"></h3>
                                </div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="text-[10px] text-zinc-400 truncate" x-text="p.barangayName || 'Bangued'"></span>
                                    <span class="text-zinc-600">•</span>
                                    <span class="inline-flex items-center gap-0.5 font-mono text-[10px] font-semibold text-emerald-400">
                                        <i class="ph ph-arrow-up-right text-[10px]"></i>
                                        +<span x-text="p.distanceM"></span>m
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Card Action Buttons -->
                        <div class="flex items-center gap-1 shrink-0">
                            <button
                                type="button"
                                class="btn btn-ghost !p-1 text-zinc-400 hover:text-emerald-400 hover:bg-emerald-500/10"
                                @click="focusDraggedPin(p)"
                                title="Center on map"
                                aria-label="Center on map"
                            >
                                <i class="ph ph-crosshair text-sm"></i>
                            </button>
                            <button
                                type="button"
                                class="btn btn-ghost !p-1 text-zinc-400 hover:text-amber-400 hover:bg-amber-500/10"
                                @click="revertSinglePin(p.placeId)"
                                title="Revert this pin"
                                aria-label="Revert this pin"
                            >
                                <i class="ph ph-arrow-u-up-left text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Coordinates Delta -->
                    <div class="mt-2 pt-2 border-t border-white/5 flex items-center justify-between text-[10px] font-mono text-zinc-400">
                        <span class="truncate text-zinc-500" x-text="p.originLat.toFixed(5) + ', ' + p.originLng.toFixed(5)"></span>
                        <i class="ph ph-arrow-right text-[10px] text-zinc-600 px-1 shrink-0"></i>
                        <span class="truncate text-emerald-400 font-medium" x-text="p.newLat.toFixed(5) + ', ' + p.newLng.toFixed(5)"></span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Sticky Footer Action Bar -->
        <div
            x-show="draggedPins.length > 0"
            class="p-3 border-t border-white/10 bg-zinc-950/80 backdrop-blur-md flex items-center gap-2 shrink-0"
        >
            <button
                type="button"
                class="btn flex-1 !py-2 text-xs font-semibold !bg-emerald-500 !text-zinc-950 hover:!bg-emerald-400 shadow-md shadow-emerald-500/20 disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-1.5"
                @click="saveAllDraggedPins()"
                :disabled="savingMassDrag"
            >
                <i class="ph ph-floppy-disk text-sm" x-show="!savingMassDrag"></i>
                <i class="ph ph-spinner animate-spin text-sm" x-show="savingMassDrag"></i>
                <span x-text="savingMassDrag ? 'Saving moves...' : 'Save All (' + draggedPins.length + ')'"></span>
            </button>

            <button
                type="button"
                class="btn btn-ghost !py-2 text-xs text-zinc-300 hover:text-red-400 whitespace-nowrap"
                @click="revertAllPins()"
                :disabled="savingMassDrag"
                title="Discard all staged moves"
            >
                Discard All
            </button>
        </div>
    </aside>
</div>
