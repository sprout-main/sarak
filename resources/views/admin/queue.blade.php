@extends('admin.layout')

@section('title', 'Moderation Queue')
@section('subtitle', 'Focus a row, press A to approve or R to reject')

@section('content')
<div x-data="adminQueue()" x-init="init()" class="mx-auto max-w-7xl space-y-4">
    <div class="panel">
        <div class="flex flex-wrap items-center gap-3 px-3 py-3">
            <div class="flex flex-wrap gap-1 rounded-full p-1" style="border:1px solid var(--line);">
                <template x-for="s in ['pending', 'approved', 'rejected']" :key="s">
                    <button type="button"
                        x-on:click="setStatus(s)"
                        x-bind:class="status === s ? 'text-[#052e16]' : 'text-zinc-400 hover:text-zinc-200'"
                        x-bind:style="status === s ? 'background:#4ade80;' : ''"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition-opacity">
                        <span class="capitalize" x-text="s"></span>
                        <span class="ml-1.5 font-mono" x-text="counts[s] !== null ? counts[s] : ''"></span>
                    </button>
                </template>
            </div>
            <button type="button" class="btn btn-ghost btn-sm ml-auto" x-on:click="loadAll()">
                <i class="ph ph-arrows-clockwise"></i> Refresh
            </button>
            <span class="hidden text-xs text-zinc-500 lg:inline"><kbd>a</kbd> approve <kbd>r</kbd> reject with a row focused</span>
        </div>
    </div>

    <div class="panel" x-show="!loading && error === ''">
        <div class="table-wrap">
            <table class="dense-table" x-on:keydown="rowKey($event)">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Action</th>
                        <th>Status</th>
                        <th>Requester</th>
                        <th>Created</th>
                        <th>Place</th>
                        <th>Payload</th>
                        <th>Diff</th>
                        <th style="min-width:230px;">Review</th>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loading">
                        <td colspan="9" class="px-0 py-1">
                            <div class="flex flex-col gap-2 p-4">
                                <div class="skel h-4 w-full"></div>
                                <div class="skel h-4 w-3/4"></div>
                                <div class="skel h-4 w-1/2"></div>
                            </div>
                        </td>
                    </tr>
                    <tr x-show="!loading && !items.length && error === ''">
                        <td colspan="9" class="px-4 py-10 text-center text-sm text-zinc-500">No items</td>
                    </tr>
                    <template x-for="item in items" :key="item.id">
                        <tr tabindex="0"
                            x-bind:data-id="item.id"
                            x-bind:class="busyId === item.id ? 'opacity-60' : ''">
                            <td class="mono text-zinc-500" x-text="'#' + item.id"></td>
                            <td>
                                <span class="badge" x-bind:class="badgeClass(item.action)" x-text="item.action"></span>
                            </td>
                            <td>
                                <span class="badge" x-bind:class="badgeClass(item.status)" x-text="item.status"></span>
                            </td>
                            <td>
                                <span class="text-zinc-300" x-text="item.requested_by ? item.requested_by.name : '-'"></span>
                            </td>
                            <td class="mono text-zinc-500" x-text="fmt(item.created_at)"></td>
                            <td>
                                <template x-if="item.place">
                                    <div>
                                        <div class="font-medium text-zinc-100" x-text="item.place.name"></div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="badge inline-flex items-center gap-1.5" x-bind:style="'background:' + (item.place.category ? item.place.category.color : '#71717a') + '22;color:var(--text);border:1px solid ' + (item.place.category ? item.place.category.color : '#71717a') + '44;'">
                                                <i class="ph-fill text-xs" x-bind:class="catIcon(item.place.category ? (item.place.category.icon_key || item.place.category.slug) : '')" x-bind:style="'color:' + (item.place.category ? item.place.category.color : '#71717a')"></i>
                                                <span x-text="item.place.category ? item.place.category.label : 'Uncategorized'"></span>
                                            </span>
                                            <span class="mono text-[10px] text-zinc-500" x-text="item.place.lat + ', ' + item.place.lng"></span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!item.place">
                                    <span class="text-zinc-500">No place yet</span>
                                </template>
                            </td>
                            <td>
                                <template x-for="row in payloadRows(item.payload)" :key="row.key">
                                    <div class="flex max-w-[220px] gap-1.5 text-[11px]">
                                        <span class="mono flex-shrink-0 text-zinc-500" x-text="row.key"></span>
                                        <span class="truncate text-zinc-300" x-text="row.value"></span>
                                    </div>
                                </template>
                                <template x-if="!payloadRows(item.payload).length">
                                    <span class="text-zinc-600">empty</span>
                                </template>
                            </td>
                            <td>
                                <button type="button"
                                    x-bind:class="openDiffId === item.id ? 'text-[var(--success)]' : 'text-zinc-500 hover:text-zinc-200'"
                                    x-on:click="toggleDiff(item)"
                                    class="rounded p-1 transition-opacity">
                                    <i class="ph ph-caret-down text-sm" x-bind:class="openDiffId === item.id ? 'rotate-180' : ''"></i>
                                </button>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <template x-if="item.status === 'pending'">
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" class="btn btn-xs btn-ghost" x-bind:disabled="busyId !== null" x-on:click="approve(item)" title="Approve (A)">
                                                <i class="ph ph-check"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-xs btn-danger" x-bind:disabled="busyId !== null" x-on:click="openReject(item)" title="Reject (R)">
                                                <i class="ph ph-x"></i> Reject
                                            </button>
                                            <button type="button" class="btn btn-xs btn-ghost" x-bind:disabled="busyId !== null" x-on:click="openMerge(item)" title="Merge into another place">
                                                <i class="ph ph-copy"></i> Merge
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="item.status !== 'pending'">
                                        <span class="badge badge-zinc">Reviewed</span>
                                    </template>
                                    <button type="button" class="btn btn-xs btn-ghost !text-rose-400 hover:!text-rose-300 hover:!bg-rose-500/10 border-rose-500/20" x-bind:disabled="busyId !== null" x-on:click="deleteItem(item)" title="Delete queue item">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr x-show="openDiffId === item.id" x-cloak x-transition.opacity.duration.150ms>
                            <td colspan="9" class="px-0">
                                <div class="p-4" style="background:var(--surface-2);">
                                    <template x-if="diffFor(item).length">
                                        <table class="diff-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:11rem;">Field</th>
                                                    <th>Before</th>
                                                    <th>After</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="row in diffFor(item)" :key="row.key">
                                                    <tr x-bind:class="row.changed ? 'changed' : ''">
                                                        <td class="diff-key" x-text="row.key"></td>
                                                        <td class="diff-val" x-bind:class="row.before === 'null' ? 'null' : ''" x-text="row.before"></td>
                                                        <td class="diff-val" x-bind:class="row.after === 'null' ? 'null' : ''" x-text="row.after"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </template>
                                    <template x-if="!diffFor(item).length">
                                        <span class="text-xs text-zinc-500">No diff preview for this action</span>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel px-4 py-6 text-sm" x-show="!loading && error !== ''" style="background:rgba(251,113,133,0.06);border-color:rgba(251,113,133,0.3);color:var(--rose);">
        <div class="flex items-center gap-2"><i class="ph ph-warning"></i><span x-text="error"></span></div>
    </div>

    <div x-cloak x-show="rejectId !== null" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
        <div class="modal-backdrop" x-on:click="closeReject()"></div>
        <div class="modal-panel panel w-full max-w-md">
            <div class="modal-head">
                <div>
                    <div class="flex items-center gap-2 text-sm font-semibold text-white">
                        <i class="ph ph-x text-base" style="color:var(--danger);"></i> Reject item <span class="mono text-zinc-500" x-text="rejectId ? '#' + rejectId : ''"></span>
                    </div>
                    <div class="mt-0.5 text-[11px] text-zinc-500">A note is required and is stored with the rejection</div>
                </div>
                <button type="button" class="btn btn-xs btn-ghost" x-on:click="closeReject()"><i class="ph ph-x"></i></button>
            </div>
            <form class="modal-body space-y-4" x-on:submit.prevent="confirmReject()">
                <div>
                    <label for="reject-note" class="field-label">Reason (required)</label>
                    <textarea id="reject-note" x-ref="rejectNote" class="input min-h-[88px]" x-model="rejectNote" placeholder="Why is this rejected?"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="btn btn-sm btn-ghost" x-on:click="closeReject()">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger" x-bind:disabled="busyId !== null">
                        <i class="ph ph-x"></i> Reject
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div x-cloak x-show="mergeId !== null" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
        <div class="modal-backdrop" x-on:click="closeMerge()"></div>
        <div class="modal-panel panel w-full max-w-md">
            <div class="modal-head">
                <div>
                    <div class="flex items-center gap-2 text-sm font-semibold text-white">
                        <i class="ph ph-copy text-base" style="color:var(--info);"></i> Merge item <span class="mono text-zinc-500" x-text="mergeId ? '#' + mergeId : ''"></span>
                    </div>
                    <div class="mt-0.5 text-[11px] text-zinc-500">Duplicate absorbed into the target place</div>
                </div>
                <button type="button" class="btn btn-xs btn-ghost" x-on:click="closeMerge()"><i class="ph ph-x"></i></button>
            </div>
            <form class="modal-body space-y-4" x-on:submit.prevent="confirmMerge()">
                <div>
                    <label for="merge-target" class="field-label">Target place ID</label>
                    <input id="merge-target" x-ref="mergeTarget" class="input" type="number" min="1" x-model="mergeTarget" placeholder="e.g. 128">
                    <p class="mt-1.5 text-[11px] text-zinc-500">Photos, hours and relations move over; the duplicate is archived.</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="btn btn-sm btn-ghost" x-on:click="closeMerge()">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-accent" x-bind:disabled="busyId !== null">
                        <i class="ph ph-copy"></i> Merge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection