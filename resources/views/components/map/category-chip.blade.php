<div class="flex items-center gap-1.5 overflow-x-auto p-1.5 rounded-full glass-strong tint-shadow max-w-full" aria-label="Category quick filters">
    <button
        type="button"
        class="chip shrink-0"
        @click="clearFilters()"
        :class="activeCategories.length === 0 && tier === 'all' && hours === 'all' && !q ? 'on' : ''"
        data-cat="all">
        <i class="ph-fill ph-grid-four text-xs"></i>
        <span>All</span>
    </button>
    <template x-for="cat in categories" :key="cat.id">
        <button
            type="button"
            class="chip shrink-0"
            :data-cat="cat.slug"
            :class="activeCategories.includes(cat.slug) ? 'on' : ''"
            @click="toggleCategory(cat.slug)">
            <i class="ph-fill text-xs shrink-0" :class="catIcon(cat.icon_key || cat.slug)" :style="'color:' + (cat.color || 'var(--cat-hue)')"></i>
            <span x-text="cat.label"></span>
            <span class="mono text-[11px] text-zinc-400 font-semibold" x-text="cat.place_count"></span>
        </button>
    </template>
</div>