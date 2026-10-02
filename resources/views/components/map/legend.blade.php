<div class="flex items-center justify-between px-4 pb-1 pt-4">
    <div class="flex items-center gap-2">
        <i class="ph ph-stack text-lg text-zinc-400"></i>
        <div>
            <p class="text-sm font-semibold text-zinc-100">Categories</p>
            <p class="text-xs text-zinc-500">Tap to show or hide a type</p>
        </div>
    </div>
    <button type="button" class="icon-btn" @click="panelOpen = !panelOpen" aria-label="Toggle category panel">
        <i class="ph" :class="panelOpen ? 'ph-caret-down' : 'ph-caret-up'"></i>
    </button>
</div>

<div class="px-3 pb-2 pt-2">
    <template x-if="categories.length === 0">
        <div class="flex flex-col gap-1.5">
            <div class="skeleton h-8 w-full"></div>
            <div class="skeleton h-8 w-full"></div>
            <div class="skeleton h-8 w-full"></div>
        </div>
    </template>

    <template x-for="cat in categories" :key="cat.id">
        <button
            type="button"
            class="lg-row"
            :data-cat="cat.slug"
            :class="activeCategories.includes(cat.slug) ? 'on' : ''"
            @click="toggleCategory(cat.slug)">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-white/10 transition-colors" :style="'background:' + (cat.color || 'var(--cat-hue)') + '22; color:' + (cat.color || 'var(--cat-hue)')">
                <i class="ph-fill text-sm" :class="catIcon(cat.icon_key || cat.slug)"></i>
            </span>
            <span class="font-medium text-sm text-zinc-200" x-text="cat.label"></span>
            <span class="lg-count ml-auto" x-text="cat.place_count"></span>
        </button>
    </template>
</div>