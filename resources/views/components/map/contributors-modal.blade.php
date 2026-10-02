<div
    x-show="contributorsOpen"
    x-cloak
    class="dlg-backdrop"
    @click.self="closeContributors()"
    @keydown.escape.window="closeContributors()"
>
    <div class="dlg-card map-scroll max-w-lg" role="dialog" aria-modal="true" aria-label="Community Contributors">
        <button type="button" class="icon-btn absolute right-4 top-4 z-10" @click="closeContributors()" aria-label="Close contributors modal">
            <i class="ph ph-x"></i>
        </button>

        <div class="p-5 sm:p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-400/10 text-amber-400 border border-amber-400/20">
                    <i class="ph ph-lightning text-xl"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-zinc-100">Community Contributors</h2>
                    <p class="text-xs text-zinc-400">Bangued local contributors ordered by community karma</p>
                </div>
            </div>

            <template x-if="contributorsLoading">
                <div class="space-y-3 py-4">
                    <div class="h-12 w-full animate-pulse rounded-xl bg-white/5"></div>
                    <div class="h-12 w-full animate-pulse rounded-xl bg-white/5"></div>
                    <div class="h-12 w-full animate-pulse rounded-xl bg-white/5"></div>
                </div>
            </template>

            <template x-if="!contributorsLoading && contributorsList.filter(item => item.karma_points > 0).length === 0">
                <div class="py-8 text-center text-sm text-zinc-400">
                    No active contributors with karma points yet.
                </div>
            </template>

            <div x-show="!contributorsLoading && contributorsList.filter(item => item.karma_points > 0).length > 0" class="divide-y divide-white/5 space-y-1">
                <template x-for="c in contributorsList.filter(item => item.karma_points > 0)" :key="c.id">
                    <div class="flex items-center justify-between py-3 px-2 rounded-xl transition-colors hover:bg-white/5">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                :class="c.is_head_admin ? 'text-amber-300 bg-amber-500/20 border border-amber-500/40' : (c.is_admin ? 'text-emerald-400 bg-emerald-400/15 border border-emerald-400/30' : 'text-zinc-300 bg-zinc-800 border border-zinc-700')"
                                x-text="c.name ? c.name.charAt(0).toUpperCase() : '?'"
                            ></span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-sm font-semibold text-zinc-100 truncate" x-text="c.name"></span>
                                    <template x-if="c.is_head_admin">
                                        <span class="badge font-mono uppercase text-[9px] bg-amber-500/20 text-amber-300 border border-amber-500/30 px-1.5 py-0.5 rounded">Head Admin</span>
                                    </template>
                                    <template x-if="!c.is_head_admin && c.is_admin">
                                        <span class="badge font-mono uppercase text-[9px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-1.5 py-0.5 rounded">Admin</span>
                                    </template>
                                </div>
                                <div class="text-xs text-zinc-400" x-show="c.claimed_count > 0">
                                    <span x-text="c.claimed_count"></span> verified places
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold bg-amber-400/10 text-amber-400 border border-amber-400/20 font-mono">
                                <span>⚡</span>
                                <span x-text="c.karma_points"></span>
                                <span class="text-[10px] font-normal uppercase opacity-75">karma</span>
                            </span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
