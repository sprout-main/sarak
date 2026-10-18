<div
    x-show="detailOpen"
    x-cloak
    class="place-dock"
    @keydown.escape.window="closeDetail()"
>
    <div
        class="place-dock-scrim md:hidden"
        x-show="detailOpen"
        @click="closeDetail()"
    ></div>
    <div
        x-ref="detailModal"
        class="place-dock-card map-scroll"
        role="region"
        aria-label="Place profile"
    >
        <button type="button" class="icon-btn place-dock-close" @click="closeDetail()" aria-label="Close place details">
            <i class="ph ph-x"></i>
        </button>

        <div class="p-5 sm:p-6">
            <template x-if="detailLoading && !detailPlace">
                <x-map.place-card :skeleton="true" />
            </template>

            <template x-if="detailPlace">
                <div>
                    <!-- Hero Photo Section (Above Place Title, Full Modal Width) -->
                    <div
                        class="relative w-full aspect-[16/10] sm:aspect-[16/9] max-h-72 overflow-hidden rounded-xl bg-zinc-950 border border-white/10 select-none group mb-4 spatial-hero-photo"
                        @touchstart.passive="onHeroPhotoTouchStart($event)"
                        @touchmove.passive="onHeroPhotoTouchMove($event)"
                        @touchend="onHeroPhotoTouchEnd()"
                    >
                        <!-- Active Photo Image -->
                        <template x-if="detailPlace.photos && detailPlace.photos.length">
                            <img
                                :src="photoUrlFor(detailPlace.photos[activePhotoIdx || 0])"
                                :alt="detailPlace.name"
                                class="h-full w-full object-cover"
                                :style="photoIsSwiping ? 'transform: translate3d(' + photoDragOffset + 'px, 0, 0); transition: none;' : 'transition: transform 320ms cubic-bezier(0.32, 0.72, 0, 1);'"
                            />
                        </template>

                        <!-- 3 Click Zones when photos exist -->
                        <template x-if="detailPlace.photos && detailPlace.photos.length">
                            <div class="absolute inset-0 flex items-stretch z-10">
                                <!-- Left Zone (25% width): switches to previous photo if photos.length > 1 -->
                                <button
                                    type="button"
                                    @click.stop="prevPhoto()"
                                    class="w-1/4 h-full flex items-center justify-start pl-2 transition-opacity focus:outline-none"
                                    :class="detailPlace.photos.length > 1 ? 'cursor-pointer group/left' : 'cursor-default'"
                                    :title="detailPlace.photos.length > 1 ? 'Previous photo' : ''"
                                    :aria-label="detailPlace.photos.length > 1 ? 'Previous photo' : ''"
                                >
                                    <span
                                        x-show="detailPlace.photos.length > 1"
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-black/75 text-white border border-white/15 backdrop-blur-sm opacity-0 group-hover:opacity-100 group-hover/left:opacity-100 transition-opacity shadow-md"
                                    >
                                        <i class="ph-bold ph-caret-left text-lg text-white"></i>
                                    </span>
                                </button>

                                <!-- Middle Zone (50% width): clicking displays photo fully (lightbox) -->
                                <button
                                    type="button"
                                    @click.stop="openPhotoLightbox()"
                                    class="w-1/2 h-full flex items-center justify-center cursor-zoom-in transition-opacity focus:outline-none group/mid"
                                    title="Click to view full photo"
                                    aria-label="View full photo"
                                >
                                    <span class="flex items-center gap-1.5 rounded-full bg-black/75 px-3 py-1 text-xs text-white border border-white/15 backdrop-blur-sm opacity-0 group-hover/mid:opacity-100 transition-opacity shadow-md">
                                        <i class="ph-bold ph-magnifying-glass-plus text-sm text-white"></i>
                                        <span class="text-white">Expand</span>
                                    </span>
                                </button>

                                <!-- Right Zone (25% width): switches to next photo if photos.length > 1 -->
                                <button
                                    type="button"
                                    @click.stop="nextPhoto()"
                                    class="w-1/4 h-full flex items-center justify-end pr-2 transition-opacity focus:outline-none"
                                    :class="detailPlace.photos.length > 1 ? 'cursor-pointer group/right' : 'cursor-default'"
                                    :title="detailPlace.photos.length > 1 ? 'Next photo' : ''"
                                    :aria-label="detailPlace.photos.length > 1 ? 'Next photo' : ''"
                                >
                                    <span
                                        x-show="detailPlace.photos.length > 1"
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-black/75 text-white border border-white/15 backdrop-blur-sm opacity-0 group-hover:opacity-100 group-hover/right:opacity-100 transition-opacity shadow-md"
                                    >
                                        <i class="ph-bold ph-caret-right text-lg text-white"></i>
                                    </span>
                                </button>
                            </div>
                        </template>

                        <!-- Admin Delete Photo Button (Map Hero) -->
                        <template x-if="window.SarakIsAdmin && detailPlace.photos && detailPlace.photos.length">
                            <div class="absolute top-2.5 right-2.5 z-20">
                                <button
                                    type="button"
                                    @click.stop="adminDeleteActivePhoto()"
                                    class="flex items-center gap-1.5 rounded-full bg-rose-950/85 hover:bg-rose-900 text-rose-300 hover:text-white px-2.5 py-1 text-xs font-semibold border border-rose-500/40 backdrop-blur-md transition-all shadow-lg active:scale-95 cursor-pointer"
                                    title="Delete photo as administrator"
                                >
                                    <i class="ph ph-trash text-sm"></i>
                                    <span>Delete</span>
                                </button>
                            </div>
                        </template>

                        <!-- Photo Counter Indicator -->
                        <template x-if="detailPlace.photos && detailPlace.photos.length > 1">
                            <div class="absolute bottom-2.5 right-2.5 z-20 pointer-events-none rounded-full bg-black/80 px-2.5 py-0.5 font-mono text-[11px] font-medium text-white backdrop-blur-md border border-white/15 shadow-sm">
                                <span class="text-white" x-text="(activePhotoIdx + 1)"></span> <span class="text-white/60">/</span> <span class="text-white" x-text="detailPlace.photos.length"></span>
                            </div>
                        </template>

                        <!-- Photographic Placeholder if 0 photos -->
                        <template x-if="!detailPlace.photos || detailPlace.photos.length === 0">
                            <div class="flex h-full w-full flex-col items-center justify-center gap-1.5 p-4 text-center text-xs text-zinc-500">
                                <i class="ph ph-camera text-2xl text-zinc-600"></i>
                                <span>No photos yet</span>
                            </div>
                        </template>
                    </div>

                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-0.5 font-mono text-[11px] text-zinc-300" x-text="tierSummary"></span>
                    </div>

                    <h2 class="text-xl font-bold leading-tight text-zinc-50" x-text="detailPlace.name"></h2>

                    <p class="mt-1.5 text-sm text-zinc-400" x-show="detailPlace.description" x-text="detailPlace.description"></p>

                    <p class="mt-2 text-sm text-zinc-400">
                        <i class="ph ph-map-pin mr-1 text-zinc-500"></i>
                        <span x-text="detailPlace.address ? detailPlace.address : detailPlace.barangay ? detailPlace.barangay.name : 'Bangued, Abra'"></span>
                    </p>

                    <div x-show="detailPlace.hours && detailPlace.hours.some(h => !h.is_closed)" class="mt-5">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Hours</p>
                            <p class="mono text-[11px] text-zinc-500">PHT/UTC+8</p>
                        </div>
                        <div class="overflow-hidden rounded-[10px] border border-white/10">
                            <button
                                type="button"
                                class="hours-toggle"
                                :class="detailHeadline.open ? 'ht-open' : (detailHeadline.state === 'Closed' ? 'ht-closed' : 'ht-maybe')"
                                @click="toggleHours()"
                                aria-label="Toggle operating hours"
                            >
                                <span class="ht-state" x-text="detailHeadline.state"></span>
                                <span class="ht-sep" x-show="detailHeadline.detail">|</span>
                                <span class="ht-detail" x-show="detailHeadline.detail" x-text="detailHeadline.detail"></span>
                                <i class="ph ml-auto text-xs text-zinc-400 transition-transform" :class="hoursExpanded ? 'ph-caret-up' : 'ph-caret-down'"></i>
                            </button>
                            <div x-show="hoursExpanded" x-cloak class="border-t border-white/5">
                                <template x-for="row in daysTableFor(detailPlace.hours)" :key="row.day">
                                    <div class="flex items-center justify-between border-b border-white/5 px-3 py-2 last:border-b-0" :class="row.isToday ? 'bg-emerald-400/5' : ''">
                                        <span class="text-sm" :class="row.isToday ? 'font-semibold text-emerald-400' : 'text-zinc-400'" x-text="row.label"></span>
                                        <span class="mono text-sm" :class="row.isToday ? 'text-emerald-400' : 'text-zinc-300'" x-text="row.is_closed ? 'Closed' : (row.opens ? fmtOpenRange(row) : 'Hours vary')"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex gap-2">
                        <button
                            type="button"
                            @click="startDirections(detailPlace)"
                            :disabled="routingBusy"
                            class="btn btn-primary flex-1 !py-2.5 text-xs font-semibold !bg-emerald-400 !text-zinc-950 hover:!bg-emerald-300 transition-colors">
                            <i class="ph ph-navigation-arrow" x-show="!routingBusy"></i>
                            <i class="ph ph-spinner animate-spin" x-show="routingBusy"></i>
                            <span x-text="routingBusy ? 'Routing...' : 'Directions'"></span>
                        </button>
                        <a
                            x-show="detailPlace.phone"
                            :href="telHrefFor(detailPlace.phone)"
                            class="btn btn-ghost flex-1 !py-2.5 text-xs">
                            <i class="ph ph-phone"></i> Call
                        </a>
                        <a
                            x-show="detailPlace.website"
                            :href="detailPlace.website"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-ghost flex-1 !py-2.5 text-xs">
                            <i class="ph ph-globe"></i> Website
                        </a>
                    </div>

                    <div class="mt-5 border-t border-white/10 pt-4">
                        <div class="flex flex-wrap items-center gap-3 text-[11px] text-zinc-500">
                            <!-- Category mark redesigned just like community contributed/added by admin mark -->
                            <span class="inline-flex items-center">
                                <i class="ph-fill mr-1 text-xs" :class="catIcon(detailPlace.category.icon_key || detailPlace.category.slug)" :style="'color:' + (detailPlace.category.color || 'var(--cat-hue)')"></i>
                                <span x-text="detailPlace.category.label"></span>
                            </span>
                            <!-- Added by / Community contributed mark -->
                            <span>
                                <i class="ph ph-user mr-1"></i>
                                <span x-text="detailPlace.submitted_by ? 'Added by ' + detailPlace.submitted_by.name : 'Community contributed'"></span>
                            </span>
                            <!-- Phone number -->
                            <span x-show="detailPlace.phone">
                                <i class="ph ph-phone mr-1"></i><span x-text="detailPlace.phone"></span>
                            </span>
                            <!-- Lat/Long coordinates just beside phone number -->
                            <span x-show="detailPlace.lat && detailPlace.lng" class="mono">
                                <i class="ph ph-crosshair mr-1"></i><span x-text="Number(detailPlace.lat).toFixed(5) + ', ' + Number(detailPlace.lng).toFixed(5)"></span>
                            </span>
                        </div>
                        <!-- Suggest a fix: only visible to non-admins -->
                        <template x-if="!window.SarakIsAdmin">
                            <button
                                type="button"
                                class="btn btn-outline-accent mt-3 w-full !py-2.5 text-xs"
                                @click="openSuggestFix(detailPlace.id)">
                                <i class="ph ph-pencil-simple"></i> Suggest a fix
                            </button>
                        </template>

                        <!-- Admin Direct Map Controls -->
                        <template x-if="window.SarakIsAdmin && detailPlace">
                            <div class="mt-4 pt-3 border-t border-white/10 space-y-2">
                                <div class="flex items-center justify-between text-[11px] text-zinc-400 font-semibold uppercase tracking-wider">
                                    <span class="flex items-center gap-1.5 text-emerald-400">
                                        <i class="ph ph-shield-check text-sm"></i> Admin Controls
                                    </span>
                                    <template x-if="detailPlace.status === 'pending' || detailPlace.is_pending">
                                        <span class="rounded bg-amber-500/20 border border-amber-500/30 px-1.5 py-0.5 text-[9px] font-bold text-amber-300">
                                            Pending Review
                                        </span>
                                    </template>
                                </div>

                                <!-- Pending Approve/Reject controls -->
                                <template x-if="detailPlace.status === 'pending' || detailPlace.is_pending">
                                    <div class="grid grid-cols-2 gap-2 pt-1">
                                        <button
                                            type="button"
                                            class="btn !bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950 font-bold !py-2 text-xs cursor-pointer shadow-lg"
                                            :disabled="adminBusy"
                                            @click="adminApprovePlace(detailPlace)"
                                        >
                                            <i class="ph ph-check-circle text-sm"></i> Approve
                                        </button>
                                        <button
                                            type="button"
                                            class="btn !bg-rose-500/20 hover:!bg-rose-500/30 !border-rose-500/40 text-rose-300 font-bold !py-2 text-xs cursor-pointer"
                                            :disabled="adminBusy"
                                            @click="adminRejectPlace(detailPlace)"
                                        >
                                            <i class="ph ph-x-circle text-sm"></i> Reject
                                        </button>
                                    </div>
                                </template>

                                <!-- Standard Admin Actions: Edit, Move Pin, Remove -->
                                <div class="grid grid-cols-3 gap-1.5 pt-1">
                                    <button
                                        type="button"
                                        class="btn btn-outline border-white/15 hover:border-emerald-400 text-zinc-200 hover:text-white !py-2 text-xs flex items-center justify-center gap-1 cursor-pointer"
                                        @click="adminOpenEdit(detailPlace)"
                                    >
                                        <i class="ph ph-pencil-simple text-sm text-emerald-400"></i> Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-outline border-white/15 hover:border-sky-400 text-zinc-200 hover:text-white !py-2 text-xs flex items-center justify-center gap-1 cursor-pointer"
                                        @click="adminStartMovePlace(detailPlace)"
                                    >
                                        <i class="ph ph-arrows-out-cardinal text-sm text-sky-400"></i> Move Pin
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-outline border-white/15 hover:border-rose-500/60 text-zinc-300 hover:text-rose-400 !py-2 text-xs flex items-center justify-center gap-1 cursor-pointer"
                                        :disabled="adminBusy"
                                        @click="adminDeletePlace(detailPlace)"
                                    >
                                        <i class="ph ph-trash text-sm text-rose-400"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<!-- Admin Place Edit Modal -->
<template x-teleport="body">
    <div
        x-show="adminEditOpen"
        x-cloak
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        @keydown.escape.window="adminEditOpen = false"
    >
        <div
            @click.outside="adminEditOpen = false"
            class="relative w-full max-w-lg rounded-2xl border border-white/10 bg-zinc-950 p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto"
        >
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <div class="flex items-center gap-2">
                    <i class="ph ph-pencil-simple text-lg text-emerald-400"></i>
                    <h3 class="text-base font-bold text-white">Edit Place (Admin)</h3>
                </div>
                <button type="button" @click="adminEditOpen = false" class="text-zinc-400 hover:text-white">
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>

            <form @submit.prevent="adminSaveEdit()" class="space-y-3">
                <div>
                    <label class="field-label text-xs !text-zinc-300">Name</label>
                    <input type="text" x-model="adminEditForm.name" required class="input-8 !w-full !text-xs mt-1 !bg-zinc-900 !border-white/15 !text-zinc-100 placeholder-zinc-500 focus:!border-emerald-400 focus:!ring-1 focus:!ring-emerald-400" />
                </div>

                <div>
                    <label class="field-label text-xs !text-zinc-300">Category</label>
                    <select x-model="adminEditForm.category_id" required class="select-8 !w-full !text-xs mt-1 !bg-zinc-900 !border-white/15 !text-zinc-100 focus:!border-emerald-400 focus:!ring-1 focus:!ring-emerald-400">
                        <template x-for="c in categories" :key="c.id">
                            <option :value="c.id" x-text="c.label" class="bg-zinc-900 text-zinc-100"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="field-label text-xs !text-zinc-300">Description</label>
                    <textarea x-model="adminEditForm.description" rows="2" class="textarea-8 !w-full !text-xs mt-1 !bg-zinc-900 !border-white/15 !text-zinc-100 placeholder-zinc-500 focus:!border-emerald-400 focus:!ring-1 focus:!ring-emerald-400"></textarea>
                </div>

                <div>
                    <label class="field-label text-xs !text-zinc-300">Address</label>
                    <input type="text" x-model="adminEditForm.address" class="input-8 !w-full !text-xs mt-1 !bg-zinc-900 !border-white/15 !text-zinc-100 placeholder-zinc-500 focus:!border-emerald-400 focus:!ring-1 focus:!ring-emerald-400" />
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="field-label text-xs !text-zinc-300">Phone</label>
                        <input type="text" x-model="adminEditForm.phone" class="input-8 !w-full !text-xs mt-1 !bg-zinc-900 !border-white/15 !text-zinc-100 placeholder-zinc-500 focus:!border-emerald-400 focus:!ring-1 focus:!ring-emerald-400" />
                    </div>
                    <div>
                        <label class="field-label text-xs !text-zinc-300">Website</label>
                        <input type="url" x-model="adminEditForm.website" class="input-8 !w-full !text-xs mt-1 !bg-zinc-900 !border-white/15 !text-zinc-100 placeholder-zinc-500 focus:!border-emerald-400 focus:!ring-1 focus:!ring-emerald-400" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-white/10">
                    <button type="button" @click="adminEditOpen = false" class="btn btn-ghost !py-2 text-xs">
                        Cancel
                    </button>
                    <button type="submit" :disabled="adminBusy" class="btn btn-primary !py-2 text-xs !bg-emerald-400 !text-zinc-950 font-semibold hover:!bg-emerald-300">
                        <span x-text="adminBusy ? 'Saving...' : 'Save Changes'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<!-- Spatial Full Photo Lightbox Canvas -->
<template x-teleport="body">
    <div
        x-show="photoLightboxOpen"
        x-cloak
        class="fixed inset-0 z-[99999] flex flex-col justify-between bg-black/95 backdrop-blur-xl p-3 sm:p-5 select-none spatial-lightbox-canvas overflow-hidden"
        :style="lightboxPullDownY > 0 ? 'background-color: rgba(0, 0, 0, ' + Math.max(0.4, 0.95 - (lightboxPullDownY / 400)) + ');' : ''"
        @click.self="toggleLightboxHud()"
        @keydown.window="onLightboxKeyDown($event)"
        @wheel.prevent="onLightboxWheel($event)"
        role="dialog"
        aria-modal="true"
        aria-label="High resolution photo viewer"
    >
        <!-- Screen reader live announcement region -->
        <div aria-live="polite" class="sr-only" x-text="lightboxAnnouncement"></div>

        <!-- Top Telemetry & Control HUD Bar -->
        <header
            x-show="lightboxHudVisible"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-4"
            class="relative z-30 flex items-center justify-between w-full pointer-events-auto"
        >
            <!-- Left Info Pill: Place Name, Photo Index, Cover Badge -->
            <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-full spatial-hud-pill min-w-0 max-w-[calc(100vw-75px)] sm:max-w-md md:max-w-lg lg:max-w-xl shadow-lg shrink">
                <span
                    :title="detailPlace ? detailPlace.name : ''"
                    class="font-semibold text-white text-xs sm:text-sm truncate block min-w-0 flex-1"
                    x-text="detailPlace ? detailPlace.name : ''"
                ></span>
                <template x-if="detailPlace && detailPlace.photos && detailPlace.photos.length > 1">
                    <span class="rounded-full bg-white/10 px-2 py-0.5 font-mono text-[10px] sm:text-[11px] text-zinc-200 shrink-0">
                        <span x-text="(activePhotoIdx + 1)"></span> / <span x-text="detailPlace.photos.length"></span>
                    </span>
                </template>
                <template x-if="detailPlace && detailPlace.photos && detailPlace.photos[activePhotoIdx] && detailPlace.photos[activePhotoIdx].is_cover">
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 px-2 py-0.5 text-[10px] font-semibold shrink-0">
                        <i class="ph-fill ph-star text-amber-400"></i> Primary
                    </span>
                </template>
            </div>

            <!-- Right Controls: Zoom Controls & Close Button -->
            <div class="flex items-center gap-2 shrink-0 ml-auto pl-2">
                <!-- Zoom Controls Pill -->
                <div class="hidden sm:flex items-center gap-1 px-2.5 py-1 rounded-full spatial-hud-pill shadow-lg text-white">
                    <button
                        type="button"
                        @click.stop="zoomOutLightbox()"
                        :disabled="lightboxZoom <= 1.05"
                        class="flex h-7 w-7 items-center justify-center rounded-full hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed transition-colors text-white"
                        title="Zoom out (Minus or J)"
                        aria-label="Zoom out"
                    >
                        <i class="ph-bold ph-minus text-sm text-white"></i>
                    </button>
                    <button
                        type="button"
                        @click.stop="toggleLightboxZoom($event)"
                        class="px-2 py-0.5 font-mono text-[11px] font-medium text-white hover:text-emerald-400 transition-colors"
                        title="Toggle zoom 1x / 2.5x (Spacebar)"
                    >
                        <span class="text-white" x-text="Math.round(lightboxZoom * 100) + '%'"></span>
                    </button>
                    <button
                        type="button"
                        @click.stop="zoomInLightbox()"
                        :disabled="lightboxZoom >= 3.45"
                        class="flex h-7 w-7 items-center justify-center rounded-full hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed transition-colors text-white"
                        title="Zoom in (Plus or K)"
                        aria-label="Zoom in"
                    >
                        <i class="ph-bold ph-plus text-sm text-white"></i>
                    </button>
                    <button
                        type="button"
                        x-show="lightboxZoom > 1.05"
                        @click.stop="resetLightboxTransform()"
                        class="ml-1 px-2.5 py-0.5 rounded-full bg-emerald-500/25 text-emerald-300 hover:bg-emerald-500/40 text-[10px] font-mono font-medium transition-colors"
                        title="Reset zoom (0)"
                    >
                        Reset
                    </button>
                </div>

                <!-- Admin Photo Delete Action -->
                <template x-if="window.SarakIsAdmin && detailPlace && detailPlace.photos && detailPlace.photos.length">
                    <button
                        type="button"
                        @click.stop="adminDeleteActivePhoto()"
                        class="flex h-10 px-3.5 shrink-0 items-center justify-center gap-1.5 rounded-full bg-rose-950/85 hover:bg-rose-900 text-rose-300 hover:text-white border border-rose-500/40 transition-all cursor-pointer shadow-xl active:scale-95 z-50 text-xs font-semibold"
                        title="Delete photo as administrator"
                        aria-label="Delete photo as administrator"
                    >
                        <i class="ph-bold ph-trash text-base"></i>
                        <span class="hidden sm:inline">Delete Photo</span>
                    </button>
                </template>

                <!-- Close Button -->
                <button
                    type="button"
                    @click.stop="closePhotoLightbox()"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-900/95 text-white border border-white/20 hover:bg-white/25 transition-all cursor-pointer shadow-xl active:scale-95 z-50"
                    title="Close (Escape)"
                    aria-label="Close full photo view"
                >
                    <i class="ph-bold ph-x text-xl text-white"></i>
                </button>
            </div>
        </header>

        <!-- Floating Tactile Navigation Chevrons Layer (High Z-Index, Drag-Isolated) -->
        <div
            x-show="lightboxHudVisible && detailPlace && detailPlace.photos && detailPlace.photos.length > 1"
            class="pointer-events-none absolute inset-x-2 sm:inset-x-4 top-1/2 -translate-y-1/2 z-40 flex items-center justify-between"
        >
            <!-- Previous Photo Tactile Button -->
            <button
                type="button"
                @click.stop="prevPhoto()"
                @mousedown.stop
                @touchstart.stop
                class="pointer-events-auto flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-full spatial-hud-pill hover:bg-white/20 text-white transition-all cursor-pointer shadow-2xl active:scale-95"
                title="Previous photo (Left arrow or H)"
                aria-label="Previous photo"
            >
                <i class="ph-bold ph-caret-left text-2xl text-white"></i>
            </button>

            <!-- Next Photo Tactile Button -->
            <button
                type="button"
                @click.stop="nextPhoto()"
                @mousedown.stop
                @touchstart.stop
                class="pointer-events-auto flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-full spatial-hud-pill hover:bg-white/20 text-white transition-all cursor-pointer shadow-2xl active:scale-95"
                title="Next photo (Right arrow or L)"
                aria-label="Next photo"
            >
                <i class="ph-bold ph-caret-right text-2xl text-white"></i>
            </button>
        </div>

        <!-- Center Interactive Photo Canvas -->
        <main
            class="relative flex-1 w-full flex items-center justify-center overflow-hidden my-auto"
            @touchstart="startLightboxDrag($event)"
            @touchmove="moveLightboxDrag($event)"
            @touchend="endLightboxDrag()"
            @mousedown="startLightboxDrag($event)"
            @mousemove="moveLightboxDrag($event)"
            @mouseup="endLightboxDrag()"
            @mouseleave="endLightboxDrag()"
            @click.self="toggleLightboxHud()"
        >
            <!-- Main High Resolution Image -->
            <template x-if="detailPlace && detailPlace.photos && detailPlace.photos.length">
                <img
                    :src="photoUrlFor(detailPlace.photos[activePhotoIdx || 0])"
                    :alt="detailPlace.name"
                    @dblclick.stop="toggleLightboxZoom($event)"
                    @dragstart.prevent
                    class="spatial-lightbox-img rounded-lg shadow-2xl border border-white/10 select-none pointer-events-auto"
                    :style="'transform: translate3d(' + lightboxPanX + 'px, ' + (lightboxPanY + lightboxPullDownY) + 'px, 0) scale(' + lightboxZoom + ');' + (lightboxIsPanning || lightboxIsPullingDown ? 'transition: none;' : 'transition: transform 320ms cubic-bezier(0.32, 0.72, 0, 1);') + (lightboxZoom > 1.05 ? (lightboxIsPanning ? 'cursor: grabbing;' : 'cursor: grab;') : 'cursor: zoom-in;')"
                />
            </template>
        </main>

        <!-- Bottom Filmstrip Scrubber & Telemetry HUD Bar -->
        <footer
            x-show="lightboxHudVisible && detailPlace && detailPlace.photos && detailPlace.photos.length > 1"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-4"
            class="relative z-30 flex flex-col items-center gap-2 w-full pointer-events-auto mt-auto"
        >
            <!-- Filmstrip Scrubber Container -->
            <div class="flex items-center gap-2 max-w-full sm:max-w-2xl px-3 py-2 rounded-2xl spatial-hud-pill spatial-filmstrip-scroll overflow-x-auto shadow-2xl">
                <template x-for="(photo, idx) in detailPlace.photos" :key="photo.id || idx">
                    <button
                        type="button"
                        :data-filmstrip-idx="idx"
                        @click.stop="goToPhoto(idx)"
                        class="relative flex-shrink-0 h-12 w-12 sm:h-14 sm:w-14 rounded-lg overflow-hidden border-2 transition-all cursor-pointer focus:outline-none focus:ring-2 focus:ring-emerald-400"
                        :class="idx === activePhotoIdx ? 'border-emerald-400 scale-105 spatial-active-thumb opacity-100' : 'border-white/10 hover:border-white/40 opacity-50 hover:opacity-90'"
                        :aria-label="'Switch to photo ' + (idx + 1)"
                    >
                        <img
                            :src="photoUrlFor(photo)"
                            :alt="'Thumbnail ' + (idx + 1)"
                            class="h-full w-full object-cover select-none pointer-events-none"
                            loading="lazy"
                        />
                        <template x-if="photo.is_cover">
                            <span class="absolute top-0.5 right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-amber-500 text-[8px] text-white shadow-sm" title="Cover Photo">
                                <i class="ph-fill ph-star"></i>
                            </span>
                        </template>
                    </button>
                </template>
            </div>

            <!-- Keyboard shortcut hint for desktop -->
            <p class="hidden sm:block text-[11px] text-zinc-400 font-mono">
                <span>Arrows or H/L navigate</span>
                <span class="mx-1.5 opacity-40">|</span>
                <span>Space / Double-click zoom</span>
                <span class="mx-1.5 opacity-40">|</span>
                <span>Esc close</span>
            </p>
        </footer>
    </div>
</template>