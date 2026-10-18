@extends('admin.layout')

@section('title', 'Places')
@section('subtitle', 'Search, edit details, and drag-correct locations')

@section('content')
<div x-data="adminPlaces()" x-init="init()" class="mx-auto max-w-7xl space-y-4">
    <div class="panel">
        <div class="flex flex-wrap items-center gap-3 px-4 py-3">
            <div class="relative min-w-[220px] max-w-sm flex-1">
                <input type="search" x-model="q" x-on:input="onSearch()" class="input" placeholder="Search name or address">
            </div>
            <div class="w-44 shrink-0">
                <select class="select !w-full" x-model="category" x-on:change="setPage(1)">
                    <option value="">All categories</option>
                    <template x-for="c in categories" :key="c.id">
                        <option x-bind:value="c.id" x-text="c.label"></option>
                    </template>
                </select>
            </div>
            <div class="w-36 shrink-0">
                <select class="select !w-full" x-model="status" x-on:change="setPage(1)">
                    <option value="">All status</option>
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
            <button type="button" class="btn btn-sm !bg-emerald-500 hover:!bg-emerald-400 !text-zinc-950 font-semibold shadow-md shadow-emerald-500/20 cursor-pointer whitespace-nowrap" x-on:click="window.dispatchEvent(new CustomEvent('open-admin-submit-place'))">
                <i class="ph ph-plus-circle"></i> Add a Place
            </button>
            <button type="button" class="btn btn-ghost btn-sm whitespace-nowrap" x-on:click="reload()">
                <i class="ph ph-arrows-clockwise"></i> Refresh
            </button>
            <span class="mono text-xs text-zinc-600 shrink-0" x-text="meta && meta.total != null ? meta.total + ' rows' : ''"></span>
        </div>
    </div>

    <div class="panel">
        <div class="table-wrap">
            <table class="dense-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Barangay</th>
                        <th>Coordinates</th>
                        <th>Status</th>
                        <th>Source</th>
                        <th>Tier</th>
                        <th>Updated</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loading">
                        <td colspan="10" class="px-0 py-1">
                            <div class="flex flex-col gap-2 p-4">
                                <div class="skel h-4 w-full"></div>
                                <div class="skel h-4 w-3/4"></div>
                                <div class="skel h-4 w-1/2"></div>
                            </div>
                        </td>
                    </tr>
                    <tr x-show="!loading && error !== ''">
                        <td colspan="10" class="px-4 py-6 text-sm" style="color:var(--rose);" x-text="error"></td>
                    </tr>
                    <tr x-show="!loading && !items.length && error === ''">
                        <td colspan="10" class="px-4 py-10 text-center text-sm text-zinc-500">No places match</td>
                    </tr>
                    <template x-for="row in items" :key="row.id">
                        <tr>
                            <td class="mono text-zinc-500" x-text="'#' + row.id"></td>
                            <td>
                                <div class="font-medium text-zinc-100" x-text="row.name"></div>
                                <div class="mono text-[10px] text-zinc-600" x-text="row.slug || ''"></div>
                            </td>
                            <td>
                                <template x-if="row.category">
                                    <span class="badge inline-flex items-center gap-1.5" x-bind:style="'background:' + (row.category.color || '#71717a') + '22;color:var(--text);border:1px solid ' + (row.category.color || '#71717a') + '44;'">
                                        <i class="ph-fill text-xs" x-bind:class="catIcon(row.category.icon_key || row.category.slug)" x-bind:style="'color:' + row.category.color"></i>
                                        <span x-text="row.category.label"></span>
                                    </span>
                                </template>
                                <template x-if="!row.category">
                                    <span class="text-zinc-600">none</span>
                                </template>
                            </td>
                            <td>
                                <span class="text-zinc-400" x-text="row.barangay ? row.barangay.name : '-'"></span>
                            </td>
                            <td class="mono text-zinc-500" x-text="row.lat + ', ' + row.lng"></td>
                            <td>
                                <span class="badge" x-bind:class="badgeClass(row.status)" x-text="row.status || 'approved'"></span>
                            </td>
                            <td>
                                <span class="badge" x-bind:class="sourceBadge(row.source)" x-text="row.source || 'manual'"></span>
                            </td>
                            <td class="mono text-zinc-400" x-text="row.verified_tier || '-'"></td>
                            <td class="mono text-zinc-500" x-text="fmt(row.updated_at)"></td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" class="btn btn-xs btn-ghost" x-on:click="openEdit(row)" title="Edit details">
                                        <i class="ph ph-pencil-simple"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost" x-on:click="openDrag(row)" title="Drag to move location">
                                        <i class="ph ph-arrows-out-cardinal"></i>
                                    </button>
                                    <a x-bind:href="'/?place=' + row.id" target="_blank" class="btn btn-xs btn-ghost" title="Open on map">
                                        <i class="ph ph-map-pin"></i>
                                    </a>
                                    <button type="button" class="btn btn-xs btn-ghost text-rose-400 hover:bg-rose-500/10 hover:text-rose-300" x-on:click="confirmDelete(row)" title="Delete place">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5" style="border-top:1px solid var(--line);">
            <span class="mono text-xs text-zinc-600" x-text="meta && meta.last_page != null ? 'Page ' + meta.current_page + ' / ' + meta.last_page : ''"></span>
            <div class="flex gap-2">
                <button type="button" class="btn btn-xs btn-ghost" x-on:click="setPage(page - 1)" x-bind:disabled="page <= 1 || loading">
                    <i class="ph ph-caret-left"></i> Prev
                </button>
                <button type="button" class="btn btn-xs btn-ghost" x-on:click="setPage(page + 1)" x-bind:disabled="(meta && meta.last_page != null && page >= meta.last_page) || loading">
                    Next <i class="ph ph-caret-right"></i>
                </button>
            </div>
        </div>
    </div>

    <div x-cloak x-show="editing !== null" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4" x-transition.opacity>
        <div class="modal-backdrop" x-on:click="closeEdit()"></div>
        <div class="modal-panel panel mt-6 w-full max-w-2xl">
            <div class="modal-head">
                <div>
                    <div class="text-sm font-semibold text-white">Edit place</div>
                    <div class="mono mt-0.5 text-[11px] text-zinc-500" x-text="form.id ? '#' + form.id : ''"></div>
                </div>
                <button type="button" class="btn btn-xs btn-ghost" x-on:click="closeEdit()"><i class="ph ph-x"></i></button>
            </div>
            <div class="space-y-4 p-4" x-show="editing !== null">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="field-label">Name</label>
                        <input class="input" x-model="form.name">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label">Description</label>
                        <textarea class="input min-h-[72px]" x-model="form.description"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label">Address</label>
                        <input class="input" x-model="form.address">
                    </div>
                    <div>
                        <label class="field-label">Phone</label>
                        <input class="input" x-model="form.phone">
                    </div>
                    <div>
                        <label class="field-label">Website</label>
                        <input class="input" x-model="form.website">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label">Category</label>
                        <select class="select" x-model="form.category_id">
                            <option value="">Select category</option>
                            <template x-for="c in categories" :key="c.id">
                                <option x-bind:value="c.id" x-text="c.label"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <span class="field-label mb-0">Hours</span>
                        <span class="text-[11px] text-zinc-500">7 rows, time as HH:MM</span>
                    </div>
                    <div class="table-wrap">
                        <table class="dense-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Opens</th>
                                    <th>Closes</th>
                                    <th>Closed</th>
                                    <th style="width:100%;">Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="h in form.hours" :key="h.day">
                                    <tr>
                                        <td class="text-zinc-300" x-text="dayLabel(h.day)"></td>
                                        <td><input class="input" x-bind:id="'opens-' + h.day" type="time" x-model="h.opens" style="width:7rem;"></td>
                                        <td><input class="input" x-bind:id="'closes-' + h.day" type="time" x-model="h.closes" style="width:7rem;"></td>
                                        <td>
                                            <input type="checkbox" class="h-4 w-4 rounded accent-[#4ade80]" x-model="h.is_closed">
                                        </td>
                                        <td class="min-w-[150px]"><input class="input" x-model="h.note" placeholder="Note"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <span class="field-label mb-0">Photos</span>
                        <span class="text-[11px] text-zinc-500" x-text="photos.length + ' total'"></span>
                    </div>

                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <label class="btn btn-xs btn-ghost cursor-pointer">
                            <i class="ph ph-upload-simple"></i> Upload photo
                            <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only"
                                x-bind:id="'place-photo-input'" x-on:change="uploadPhoto($event)">
                        </label>
                        <span class="text-[11px] text-zinc-500" x-show="uploadingPhoto">Uploading&hellip;</span>
                    </div>

                    <p class="mb-2 text-[11px] text-zinc-500" x-show="photos.length === 0 && !photosLoading">
                        No photos on this place yet.
                    </p>
                    <p class="mb-2 text-[11px] text-zinc-500" x-show="photosLoading">Loading photos&hellip;</p>

                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <template x-for="(p, idx) in photos" :key="p.id">
                            <figure class="relative overflow-hidden rounded-lg border group/photo" x-bind:class="p.is_cover ? 'border-emerald-400/60' : 'border-white/10'">
                                <img class="h-24 w-full object-cover" x-bind:src="photoUrl(p)" x-bind:alt="'Place photo ' + p.id">
                                <div class="absolute left-1 top-1 flex items-center gap-0.5 z-10">
                                    <span class="rounded bg-black/80 px-1 py-0.5 text-[9px] font-mono font-bold !text-white border !border-white/20 shadow-sm" x-text="'#' + (idx + 1)"></span>
                                    <button type="button" class="btn btn-xs btn-ghost bg-black/80 !p-1 !text-zinc-200 hover:!text-white disabled:opacity-30 disabled:pointer-events-none border !border-white/20"
                                        x-on:click="movePhoto(idx, -1)"
                                        x-bind:disabled="idx === 0 || photoBusy === p.id"
                                        title="Move photo earlier">
                                        <i class="ph ph-caret-left text-xs"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost bg-black/80 !p-1 !text-zinc-200 hover:!text-white disabled:opacity-30 disabled:pointer-events-none border !border-white/20"
                                        x-on:click="movePhoto(idx, 1)"
                                        x-bind:disabled="idx === photos.length - 1 || photoBusy === p.id"
                                        title="Move photo later">
                                        <i class="ph ph-caret-right text-xs"></i>
                                    </button>
                                </div>
                                <div class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-1 px-1.5 py-1"
                                    style="background:linear-gradient(transparent, rgba(0,0,0,0.85));">
                                    <span class="rounded px-1 py-0.5 text-[10px] font-semibold"
                                        x-bind:class="{
                                            'bg-emerald-500/90 !text-black': p.status === 'approved',
                                            'bg-amber-500/90 !text-black': p.status === 'pending',
                                            'bg-rose-500/90 !text-white': p.status === 'rejected'
                                        }"
                                        x-text="p.status"></span>
                                    <span class="rounded bg-black/70 px-1 py-0.5 text-[10px] font-semibold text-emerald-300" x-show="p.is_cover">COVER</span>
                                </div>
                                <div class="absolute right-1 top-1 flex gap-1 z-10">
                                    <button type="button" class="btn btn-xs btn-ghost bg-black/70 !text-zinc-200 hover:!text-amber-300 border !border-white/20"
                                        x-on:click="setCoverPhoto(p)"
                                        x-bind:disabled="p.is_cover || photoBusy === p.id"
                                        title="Use as cover photo">
                                        <i class="ph ph-star"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost bg-black/70 !text-rose-400 hover:!text-rose-300 border !border-white/20"
                                        x-on:click="deletePhoto(p)"
                                        x-bind:disabled="photoBusy === p.id"
                                        title="Delete photo">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </div>
                            </figure>
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 pt-3" style="border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-sm btn-ghost text-rose-400 hover:bg-rose-500/10 hover:text-rose-300" x-on:click="confirmDelete(editing)">
                        <i class="ph ph-trash"></i> Delete place
                    </button>
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn btn-sm btn-ghost" x-on:click="closeEdit()">Cancel</button>
                        <button type="button" class="btn btn-sm btn-accent" x-on:click="saveEdit()" x-bind:disabled="saving">
                            <i class="ph ph-check"></i> Save changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-cloak x-show="dragging !== null" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4" x-transition.opacity>
        <div class="modal-backdrop"></div>
        <div class="modal-panel panel mt-6 w-full max-w-3xl">
            <div class="modal-head">
                <div>
                    <div class="text-sm font-semibold text-white">Move location</div>
                    <div class="mono mt-0.5 text-[11px] text-zinc-500" x-text="dragging ? dragging.name : ''"></div>
                </div>
                <button type="button" class="btn btn-xs btn-ghost" x-on:click="closeDrag()"><i class="ph ph-x"></i></button>
            </div>
            <div class="space-y-3 p-4">
                <div class="z-0 h-72 w-full overflow-hidden rounded-lg" style="border:1px solid var(--line);" x-ref="dragMap"></div>
                <p class="flex items-center gap-2 text-xs text-zinc-500">
                    <i class="ph ph-crosshair text-sm"></i>
                    <span>Drag the marker or type coordinates. Snap refines to the nearest street via Nominatim.</span>
                </p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="field-label">Latitude</label>
                        <input class="input mono" type="number" step="any" x-bind:value="dragLat" x-on:input="onLatInput($event)">
                    </div>
                    <div>
                        <label class="field-label">Longitude</label>
                        <input class="input mono" type="number" step="any" x-bind:value="dragLng" x-on:input="onLngInput($event)">
                    </div>
                    <div>
                        <label class="field-label">Barangay</label>
                        <select class="select" x-model="dragBarangay">
                            <option value="">Keep current</option>
                            <template x-for="b in barangays" :key="b.id">
                                <option x-bind:value="b.id" x-text="b.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Moved</label>
                        <div class="mono mt-1.5 text-sm" style="color:var(--success);" x-text="distanceLabel()"></div>
                    </div>
                </div>
                <div x-show="snapInfo !== ''" class="rounded-lg px-3 py-2 text-xs" style="background:rgba(56,189,248,0.08);color:var(--info);" x-text="snapInfo"></div>
                <div class="flex flex-wrap items-center justify-end gap-2 pt-3" style="border-top:1px solid var(--line);">
                    <span class="mr-auto text-[11px] text-zinc-600">Undo steps back through previous coordinates</span>
                    <button type="button" class="btn btn-sm btn-ghost" x-on:click="undo()" x-bind:disabled="!undoStack.length">
                        <i class="ph ph-arrow-counter-clockwise"></i> Undo
                    </button>
                    <button type="button" class="btn btn-sm btn-ghost" x-on:click="snap()">
                        <i class="ph ph-crosshair"></i> Snap to street
                    </button>
                    <button type="button" class="btn btn-sm btn-accent" x-on:click="saveLocation()">
                        <i class="ph ph-floppy-disk"></i> Save location
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Delete Confirmation Modal -->
    <div x-cloak x-show="deleting !== null" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
        <div class="modal-backdrop" x-on:click="cancelDelete()"></div>
        <div class="modal-panel panel w-full max-w-md p-6 space-y-4 shadow-2xl border border-rose-500/30">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-400">
                    <i class="ph ph-warning-circle text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-white">Delete Place</h3>
                    <p class="text-xs text-zinc-400">Permanent administrative deletion</p>
                </div>
            </div>
            <p class="text-sm text-zinc-300 leading-relaxed">
                Are you sure you want to permanently delete <strong class="text-white font-semibold" x-text="deleting ? deleting.name : ''"></strong>? This will remove all associated hours, photos, and location records.
            </p>
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-white/10">
                <button type="button" class="btn btn-sm btn-ghost" x-on:click="cancelDelete()">Cancel</button>
                <button type="button" class="btn btn-sm !bg-rose-600 !text-white hover:!bg-rose-500" x-on:click="performDelete()" x-bind:disabled="deleteBusy">
                    <i class="ph ph-trash"></i>
                    <span x-text="deleteBusy ? 'Deleting...' : 'Delete permanently'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection