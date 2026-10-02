@props(['onAdd' => 'openSubmit'])

<div
    x-show="false"
    x-cloak
    class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center p-4">
    <div class="pointer-events-auto glass-strong panel-12 tint-shadow anim-spring max-w-xs px-8 py-7 text-center">
        <div class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-zinc-900">
            <i class="ph ph-map-pin text-xl text-zinc-500"></i>
        </div>
        <p class="text-sm font-semibold text-zinc-100">Nothing here yet</p>
        <p class="mt-1 text-xs leading-relaxed text-zinc-400">No places found in this area. Help us map it.</p>
        <button type="button" class="btn btn-primary mt-4 w-full" @click="{{ $onAdd }}()">
            <i class="ph ph-plus"></i>
            Add this place
        </button>
    </div>
</div>