<div
    x-show="!routeActive"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-2"
    class="absolute left-1/2 top-16 z-30 w-[92%] max-w-xl -translate-x-1/2 sm:top-3 pointer-events-auto"
>
    <div class="relative" @click.outside="searchOpen = false">
        <input
            type="search"
            x-model="searchQ"
            @input="onSearchInput()"
            @focus="searchQ.trim() ? (searchOpen = true) : null"
            @keydown.enter="searchResults[0] && focusPlace(searchResults[0])"
            @keydown.escape="searchOpen = false"
            class="w-full rounded-full border border-white/10 bg-zinc-900/75 py-3 px-5 pr-12 text-sm text-zinc-100 backdrop-blur placeholder:text-zinc-500 focus:border-[#4ade80] focus:outline-none focus:ring-4 focus:ring-[#4ade80]/10"
            placeholder="Find a place, street, or landmark"
            aria-label="Search places"
        >
        <button
            type="button"
            class="absolute right-3.5 top-1/2 -translate-y-1/2 text-zinc-500 transition-colors hover:text-white"
            x-show="searchQ"
            @click="clearSearch()"
            aria-label="Clear search">
            <i class="ph ph-x text-base"></i>
        </button>
    </div>

    <div
        x-show="searchOpen && (searchBusy || searchResults.length > 0)"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="mt-2 overflow-hidden glass-strong panel-12 tint-shadow"
    >
        <template x-if="searchBusy">
            <div class="flex flex-col gap-3 p-4">
                <div class="skeleton h-5 w-3/4"></div>
                <div class="skeleton h-5 w-2/3"></div>
            </div>
        </template>

        <template x-if="!searchBusy && searchResults.length === 0">
            <div class="px-4 py-5 text-center">
                <p class="text-sm text-zinc-300">Nothing here yet</p>
                <p class="mt-0.5 text-xs text-zinc-500" x-text="`No results for ${searchQ}`"></p>
            </div>
        </template>

        <template x-for="r in searchResults" :key="r.id">
            <button
                type="button"
                class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-white/5"
                @click="focusPlace(r)">
                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-white/10" :style="'background:' + (r.category ? r.category.color : 'var(--cat-hue)') + '22; color:' + (r.category ? r.category.color : 'var(--cat-hue)')">
                    <i class="ph-fill text-xs" :class="catIcon(r.category ? (r.category.icon_key || r.category.slug) : '')"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-zinc-100" x-text="r.name"></span>
                    <span class="block truncate text-xs text-zinc-500" x-text="r.address || (r.category ? r.category.label : '')"></span>
                </span>
                <span class="mono shrink-0 text-xs text-zinc-500" x-text="fd(r.distance_m) || ''"></span>
            </button>
        </template>
    </div>
</div>