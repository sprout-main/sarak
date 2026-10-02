<!-- Floating In-App Route & Directions HUD -->
<div
    x-show="routeActive && routeSummary"
    x-cloak
    x-transition:enter="transition ease-out duration-250"
    x-transition:enter-start="opacity-0 -translate-y-4 scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
    x-transition:leave-end="opacity-0 -translate-y-4 scale-95"
    class="absolute top-4 left-1/2 z-40 -translate-x-1/2 w-[94vw] max-w-md pointer-events-auto"
>
    <div class="glass-strong panel-12 p-3.5 sm:p-4 tint-shadow border border-emerald-500/30 shadow-2xl space-y-3">
        <!-- Top bar: Destination Place Name & Close Route Button -->
        <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-2.5">
            <div class="flex items-center gap-2 min-w-0">
                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                    <i class="ph ph-navigation-arrow text-sm"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-[10px] font-mono uppercase tracking-wider text-emerald-400 font-semibold flex items-center gap-1">
                        <span>Route Active</span>
                        <template x-if="routeSummary && routeSummary.source === 'local_graph'">
                            <span class="text-cyan-400 font-medium normal-case">(Local Road Graph)</span>
                        </template>
                        <template x-if="routeSummary && routeSummary.is_fallback && routeSummary.source !== 'local_graph'">
                            <span class="text-amber-400 font-normal normal-case">(straight line)</span>
                        </template>
                    </div>
                    <h4 class="text-xs sm:text-sm font-bold text-white truncate" x-text="routeDestination ? routeDestination.name : 'Destination'"></h4>
                </div>
            </div>

            <button
                type="button"
                @click="clearRoute()"
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-zinc-800/80 text-zinc-400 hover:text-white hover:bg-zinc-700/80 transition-colors"
                title="Exit directions"
                aria-label="Clear route"
            >
                <i class="ph ph-x text-sm"></i>
            </button>
        </div>

        <!-- Metric telemetry: Big duration & distance -->
        <div class="flex items-baseline justify-between gap-3 px-1">
            <div class="flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white font-mono" x-text="routeSummary ? routeSummary.duration_text : '--'"></span>
                <span class="text-xs font-semibold text-emerald-400" x-text="'via ' + routeProfile"></span>
            </div>
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/5 border border-white/10 text-xs font-mono text-zinc-200">
                <i class="ph ph-ruler text-zinc-400"></i>
                <span x-text="routeSummary ? routeSummary.distance_text : '--'"></span>
            </div>
        </div>

        <!-- Mode selector pills: Driving / Walking / Cycling -->
        <div class="grid grid-cols-3 gap-1.5 p-1 rounded-xl bg-zinc-950/70 border border-white/5">
            <button
                type="button"
                @click="setRouteProfile('driving')"
                class="flex flex-col items-center justify-center py-1.5 px-2 rounded-lg text-xs transition-all duration-150"
                :class="routeProfile === 'driving' ? 'bg-emerald-500 text-zinc-950 font-bold shadow-md shadow-emerald-500/20' : 'text-zinc-400 hover:text-zinc-200 hover:bg-white/5'"
            >
                <div class="flex items-center gap-1 text-[11px]">
                    <i class="ph ph-car text-sm"></i>
                    <span>Drive</span>
                </div>
                <span class="text-[10px] font-mono mt-0.5 opacity-80" x-text="routeSummary && routeSummary.durations && routeSummary.durations.driving ? routeSummary.durations.driving.text : ''"></span>
            </button>

            <button
                type="button"
                @click="setRouteProfile('walking')"
                class="flex flex-col items-center justify-center py-1.5 px-2 rounded-lg text-xs transition-all duration-150"
                :class="routeProfile === 'walking' ? 'bg-emerald-500 text-zinc-950 font-bold shadow-md shadow-emerald-500/20' : 'text-zinc-400 hover:text-zinc-200 hover:bg-white/5'"
            >
                <div class="flex items-center gap-1 text-[11px]">
                    <i class="ph ph-person-simple-walk text-sm"></i>
                    <span>Walk</span>
                </div>
                <span class="text-[10px] font-mono mt-0.5 opacity-80" x-text="routeSummary && routeSummary.durations && routeSummary.durations.walking ? routeSummary.durations.walking.text : ''"></span>
            </button>

            <button
                type="button"
                @click="setRouteProfile('cycling')"
                class="flex flex-col items-center justify-center py-1.5 px-2 rounded-lg text-xs transition-all duration-150"
                :class="routeProfile === 'cycling' ? 'bg-emerald-500 text-zinc-950 font-bold shadow-md shadow-emerald-500/20' : 'text-zinc-400 hover:text-zinc-200 hover:bg-white/5'"
            >
                <div class="flex items-center gap-1 text-[11px]">
                    <i class="ph ph-bicycle text-sm"></i>
                    <span>Cycle</span>
                </div>
                <span class="text-[10px] font-mono mt-0.5 opacity-80" x-text="routeSummary && routeSummary.durations && routeSummary.durations.cycling ? routeSummary.durations.cycling.text : ''"></span>
            </button>
        </div>

        <!-- Footer Actions -->
        <div class="flex items-center justify-between gap-2 pt-1 border-t border-white/5 text-xs">
            <button
                type="button"
                @click="if (routeDestination) openDetail(routeDestination.id)"
                class="text-zinc-400 hover:text-white inline-flex items-center gap-1 transition-colors"
            >
                <i class="ph ph-info text-sm"></i>
                <span>Place Info</span>
            </button>

            <button
                type="button"
                @click="openExternalNavigation()"
                class="text-emerald-400 hover:text-emerald-300 inline-flex items-center gap-1 font-medium transition-colors"
            >
                <span>Voice Nav (Google Maps)</span>
                <i class="ph ph-arrow-square-out text-sm"></i>
            </button>
        </div>
    </div>
</div>
