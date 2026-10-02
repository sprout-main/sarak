<div
    x-show="submitOpen"
    x-cloak
    class="dlg-backdrop"
    @click.self="closeSubmit()"
    @keydown.escape.window="closeSubmit()"
>
    <div class="dlg-card map-scroll" role="dialog" aria-modal="true" aria-label="Add a place">
        <div class="flex items-start justify-between px-5 pb-1 pt-5 sm:px-6">
            <div>
                <h3 class="text-base font-semibold text-zinc-100">Add a place</h3>
                <p class="mt-0.5 text-xs text-zinc-500" x-text="window.SarakIsAdmin ? 'Directly publishing to Sarak live map.' : 'Sent for review so Sarak stays accurate.'"></p>
            </div>
            <button type="button" class="icon-btn" @click="closeSubmit()" aria-label="Close add place form">
                <i class="ph ph-x"></i>
            </button>
        </div>

        <form class="space-y-4 px-5 py-5 sm:px-6" @submit.prevent="submitForm()">
            <div>
                <label class="field-label" for="sf-category">Category</label>
                <select class="select-8" id="sf-category" x-model="formCategoryId">
                    <option value="">Choose a category</option>
                    <template x-for="cat in categories" :key="cat.id">
                        <option :value="cat.id" x-text="cat.label"></option>
                    </template>
                </select>
                <template x-for="msg in (formErrors.category_id || [])" :key="msg">
                    <p class="mt-1 text-xs text-rose-400" x-text="msg"></p>
                </template>
            </div>

            <div>
                <label class="field-label" for="sf-name">Name</label>
                <input class="input-8" id="sf-name" type="text" x-model="formName" :readonly="readonlyName" placeholder="e.g. Don Mariano Marcos Memorial Hospital">
                <template x-for="msg in (formErrors.name || [])" :key="msg">
                    <p class="mt-1 text-xs text-rose-400" x-text="msg"></p>
                </template>
                <p class="mt-1 text-xs text-zinc-500" x-show="readonlyName">This name has been pre-filled from the place you opened.</p>
            </div>

            <div>
                <label class="field-label" for="sf-desc">Description <span class="text-zinc-500 normal-case">optional</span></label>
                <textarea class="textarea-8 min-h-[80px]" id="sf-desc" x-model="formDesc"></textarea>
            </div>

            <div>
                <label class="field-label" for="sf-address">Address <span class="text-zinc-500 normal-case">optional</span></label>
                <input class="input-8" id="sf-address" type="text" x-model="formAddress" placeholder="Street or landmark">
                <template x-for="msg in (formErrors.address || [])" :key="msg">
                    <p class="mt-1 text-xs text-rose-400" x-text="msg"></p>
                </template>
            </div>

            <!-- Auto-resolved Barangay & Dropped Pin Location -->
            <input type="hidden" x-model="formLat">
            <input type="hidden" x-model="formLng">
            <input type="hidden" x-model="formBarangayId">

            <div class="rounded-xl border border-white/10 bg-zinc-900/60 p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <span class="field-label mb-0">Pinned Location</span>
                    <template x-if="formLat && formLng">
                        <span class="inline-flex items-center gap-1 text-[11px] font-mono text-emerald-400 bg-emerald-400/10 px-2 py-0.5 rounded-full border border-emerald-400/20">
                            <i class="ph ph-check-circle"></i> Pinned
                        </span>
                    </template>
                </div>

                <div class="flex items-center justify-between gap-3 p-3 rounded-lg border border-white/5 bg-zinc-950/60 mb-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-400">
                                <i class="ph ph-buildings"></i>
                                <span x-text="resolvedBarangayName"></span>
                            </span>
                        </div>
                        <p class="font-mono text-xs text-zinc-400 mt-0.5 truncate" x-text="formLat && formLng ? (formLat + ', ' + formLng) : 'Drop pin on map to set coordinates'"></p>
                    </div>

                    <button
                        type="button"
                        class="btn btn-ghost !py-1.5 !px-3 text-xs shrink-0 flex items-center gap-1.5 border border-white/10 hover:border-emerald-400/40 hover:text-emerald-300 transition-colors"
                        @click="startPickingLocation()"
                        title="Adjust pin position on map"
                    >
                        <i class="ph ph-map-pin text-sm text-emerald-400"></i>
                        <span>Reposition</span>
                    </button>
                </div>

                <div class="flex items-center justify-between">
                    <button
                        type="button"
                        class="text-xs text-sky-400 hover:text-sky-300 inline-flex items-center gap-1.5 transition-colors cursor-pointer"
                        @click="useMyLocation()"
                        :disabled="formLocating"
                    >
                        <i class="ph ph-crosshair text-sm"></i>
                        <span x-text="formLocating ? 'Acquiring GPS...' : 'Use current GPS location'"></span>
                    </button>
                    <span class="text-[11px] text-zinc-500">Spatial lookup auto-selects Barangay</span>
                </div>

                <template x-for="msg in (formErrors.lat || formErrors.lng || [])" :key="msg">
                    <p class="mt-2 text-xs text-rose-400" x-text="msg"></p>
                </template>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="field-label" for="sf-phone">Phone <span class="text-zinc-500 normal-case">optional</span></label>
                    <input class="input-8" id="sf-phone" type="tel" x-model="formPhone">
                </div>
                <div>
                    <label class="field-label" for="sf-web">Website <span class="text-zinc-500 normal-case">optional</span></label>
                    <input class="input-8" id="sf-web" type="url" x-model="formWebsite" placeholder="https://">
                </div>
            </div>

            <div class="rounded-[12px] border border-white/10 bg-zinc-900/60 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <span class="field-label mb-0">Opening hours</span>
                    <span class="mono text-[11px] text-zinc-500">PHT/UTC+8</span>
                </div>
                <div class="space-y-2">
                    <template x-for="row in hoursRows" :key="row.day">
                        <div class="flex items-center gap-2">
                            <span class="w-16 shrink-0 text-xs font-medium" :class="!row.is_closed ? 'text-zinc-200' : 'text-zinc-500'" x-text="row.label"></span>
                            <label class="inline-flex shrink-0 cursor-pointer items-center gap-1.5">
                                <input type="checkbox" class="h-3.5 w-3.5 rounded accent-emerald-400" x-model="row.is_closed">
                                <span class="text-[11px]" :class="row.is_closed ? 'text-zinc-500' : 'text-emerald-400'" x-text="row.is_closed ? 'Closed' : 'Open'"></span>
                            </label>
                            <template x-if="!row.is_closed">
                                <div class="flex items-center gap-1">
                                    <input type="time" class="input-8 !w-auto !py-1.5 text-xs" x-model="row.opens" x-bind:disabled="row.is_closed">
                                    <span class="text-zinc-600">-</span>
                                    <input type="time" class="input-8 !w-auto !py-1.5 text-xs" x-model="row.closes" x-bind:disabled="row.is_closed">
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <div>
                <span class="field-label">Photo <span class="text-zinc-500 normal-case">optional</span></span>
                <label class="flex cursor-pointer items-center gap-3 rounded-[10px] border border-dashed border-white/15 px-4 py-3 transition-colors hover:border-white/30">
                    <i class="ph ph-camera text-xl text-zinc-500"></i>
                    <span class="flex-1 truncate text-sm text-zinc-400" x-text="photoName || 'Choose a photo file'"></span>
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onPhotoPick($event)">
                </label>
                <p class="mt-1 text-xs text-zinc-500" x-text="window.SarakIsAdmin ? 'Photo will be attached directly upon creation.' : 'Photos are added after the place goes live.'"></p>
            </div>

            <div class="pt-1">
                <template x-for="msg in (formErrors.message || [])" :key="msg">
                    <p class="mb-2 text-xs text-rose-400" x-text="msg"></p>
                </template>
                <button type="submit" class="btn btn-primary w-full !py-3" :disabled="formBusy">
                    <i class="ph" :class="window.SarakIsAdmin ? 'ph-check-circle' : 'ph-paper-plane-tilt'" text-base x-show="!formBusy"></i>
                    <span x-text="formBusy ? 'Submitting...' : (window.SarakIsAdmin ? 'Add a place' : 'Submit for review')"></span>
                </button>
                <p class="mt-3 text-center text-[11px] leading-relaxed text-zinc-500" x-text="window.SarakIsAdmin ? 'As an administrator, this place will be published immediately without queue review.' : 'Your submission enters a moderation queue before it appears on the public map.'">
                </p>
            </div>
        </form>
    </div>
</div>