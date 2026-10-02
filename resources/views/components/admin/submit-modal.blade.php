<div x-data="adminSubmitPlace()" x-init="init()" x-cloak>
    <div
        x-show="open"
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-6"
        x-transition.opacity>
        <div class="modal-backdrop" @click="closeModal()"></div>
        <div class="modal-panel panel mt-4 sm:mt-8 w-full max-w-2xl sm:max-w-3xl border border-white/10 shadow-2xl">
            <div class="modal-head">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-400">
                        <i class="ph ph-plus-circle text-lg"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">Add a Place</div>
                        <div class="text-[11px] text-zinc-400">Add municipal infrastructure directly with interactive minimap pin placement</div>
                    </div>
                </div>
                <button type="button" class="btn btn-xs btn-ghost text-zinc-400 hover:text-white" @click="closeModal()">
                    <i class="ph ph-x text-base"></i>
                </button>
            </div>

            <form @submit.prevent="submit()" class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="field-label">Place Name <span class="text-rose-400">*</span></label>
                        <input type="text" x-model="form.name" class="input w-full" placeholder="e.g. Bangued General Hospital" required>
                        <template x-for="err in (errors.name || [])" :key="err">
                            <p class="text-[11px] text-rose-400 mt-1" x-text="err"></p>
                        </template>
                    </div>

                    <div>
                        <label class="field-label">Category <span class="text-rose-400">*</span></label>
                        <select x-model="form.category_id" class="select w-full" required>
                            <option value="">Select Category</option>
                            <template x-for="cat in categories" :key="cat.id">
                                <option :value="cat.id" x-text="cat.label"></option>
                            </template>
                        </select>
                        <template x-for="err in (errors.category_id || [])" :key="err">
                            <p class="text-[11px] text-rose-400 mt-1" x-text="err"></p>
                        </template>
                    </div>

                    <div>
                        <label class="field-label">Barangay <span class="text-zinc-500">(auto-snaps if omitted)</span></label>
                        <select x-model="form.barangay_id" class="select w-full">
                            <option value="">Auto-detect from coordinates</option>
                            <template x-for="b in barangays" :key="b.id">
                                <option :value="b.id" x-text="b.name"></option>
                            </template>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="field-label">Street Address <span class="text-zinc-500">(optional)</span></label>
                        <input type="text" x-model="form.address" class="input w-full" placeholder="e.g. Taft St., Zone 5">
                        <template x-for="err in (errors.address || [])" :key="err">
                            <p class="text-[11px] text-rose-400 mt-1" x-text="err"></p>
                        </template>
                    </div>

                    <!-- Minimap & Drop a Pin Section -->
                    <div class="sm:col-span-2 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="field-label flex items-center gap-1.5 !mb-0 text-xs font-semibold text-zinc-200">
                                <i class="ph ph-map-pin text-emerald-400 text-sm"></i>
                                <span>Drop a Pin / Minimap</span>
                                <span class="text-[11px] font-normal text-zinc-400 hidden sm:inline">(click map or drag pin to position)</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="recenterBangued()" class="text-[11px] text-emerald-400 hover:text-emerald-300 font-medium flex items-center gap-1 cursor-pointer transition-colors">
                                    <i class="ph ph-crosshair"></i> Recenter
                                </button>
                                <button type="button" @click="useMyLocation()" class="text-[11px] text-zinc-400 hover:text-white font-medium flex items-center gap-1 cursor-pointer transition-colors" :disabled="locating">
                                    <i class="ph" :class="locating ? 'ph-spinner animate-spin text-emerald-400' : 'ph-navigation-arrow'"></i>
                                    <span x-text="locating ? 'Locating...' : 'GPS'"></span>
                                </button>
                            </div>
                        </div>

                        <div class="relative w-full h-52 sm:h-60 rounded-xl overflow-hidden border border-white/10 shadow-inner bg-zinc-950">
                            <div x-ref="miniMap" class="w-full h-full z-0"></div>
                            <div class="absolute bottom-2 left-2 z-[400] bg-zinc-900/90 backdrop-blur-md px-2.5 py-1 rounded-md text-[11px] text-zinc-300 border border-white/10 pointer-events-none flex items-center gap-1.5 shadow-lg">
                                <i class="ph ph-hand-pointing text-emerald-400"></i> Click map or drag pin to position
                            </div>
                            <div class="absolute top-2 right-2 z-[400] bg-zinc-900/90 backdrop-blur-md px-2 py-0.5 rounded text-[10px] font-mono text-zinc-300 border border-white/10 pointer-events-none">
                                <span x-text="form.lat || '0'"></span>, <span x-text="form.lng || '0'"></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="field-label">Latitude <span class="text-rose-400">*</span></label>
                        <input type="number" step="any" x-model="form.lat" @input="updateMapFromInputs()" class="input mono w-full" placeholder="17.5965" required>
                        <template x-for="err in (errors.lat || [])" :key="err">
                            <p class="text-[11px] text-rose-400 mt-1" x-text="err"></p>
                        </template>
                    </div>

                    <div>
                        <label class="field-label">Longitude <span class="text-rose-400">*</span></label>
                        <input type="number" step="any" x-model="form.lng" @input="updateMapFromInputs()" class="input mono w-full" placeholder="120.6190" required>
                        <template x-for="err in (errors.lng || [])" :key="err">
                            <p class="text-[11px] text-rose-400 mt-1" x-text="err"></p>
                        </template>
                    </div>

                    <div>
                        <label class="field-label">Contact Phone <span class="text-zinc-500">(optional)</span></label>
                        <input type="text" x-model="form.phone" class="input w-full" placeholder="e.g. 0917 123 4567">
                    </div>

                    <div>
                        <label class="field-label">Website or Social <span class="text-zinc-500">(optional)</span></label>
                        <input type="url" x-model="form.website" class="input w-full" placeholder="https://...">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="field-label">Description <span class="text-zinc-500">(optional)</span></label>
                        <textarea x-model="form.description" class="input w-full" rows="2" placeholder="Brief infrastructure summary or public amenities"></textarea>
                    </div>

                    <!-- Photo Section -->
                    <div class="sm:col-span-2">
                        <label class="field-label">Photos <span class="text-zinc-500">(optional)</span></label>
                        <div class="flex flex-col sm:flex-row items-center gap-3">
                            <label class="flex flex-1 w-full cursor-pointer items-center gap-3 rounded-lg border border-dashed border-white/15 px-4 py-2.5 transition-colors hover:border-emerald-500/40 bg-white/5">
                                <i class="ph ph-camera text-xl text-emerald-400"></i>
                                <span class="flex-1 truncate text-xs text-zinc-300" x-text="photoName || 'Choose a photo file (JPG, PNG, WebP)'"></span>
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onPhotoSelected($event)">
                            </label>
                            <template x-if="photoPreview">
                                <div class="relative shrink-0">
                                    <img :src="photoPreview" alt="Preview" class="h-12 w-12 rounded-lg object-cover ring-1 ring-white/20 shadow-md" />
                                    <button type="button" @click="clearPhoto()" class="absolute -top-1.5 -right-1.5 h-4 w-4 rounded-full bg-rose-500 text-white flex items-center justify-center text-[10px] hover:bg-rose-600 transition-colors">
                                        <i class="ph ph-x"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-white/10">
                    <button type="button" class="btn btn-sm btn-ghost" @click="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-accent" :disabled="submitting">
                        <i class="ph" :class="submitting ? 'ph-spinner animate-spin' : 'ph-plus-circle'"></i>
                        <span x-text="submitting ? 'Adding...' : 'Add Place'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
