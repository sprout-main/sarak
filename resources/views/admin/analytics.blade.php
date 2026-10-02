@extends('admin.layout')

@section('title', 'Analytics')
@section('subtitle', 'Bangued town infrastructure overview, category distribution, and moderation activity')

@section('content')
<div x-data="adminAnalytics()" x-init="init()" class="mx-auto max-w-7xl space-y-4">
    <div x-show="loading">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="skel h-24"></div>
            <div class="skel h-24"></div>
            <div class="skel h-24"></div>
            <div class="skel h-24"></div>
        </div>
        <div class="mt-4 skel h-72"></div>
    </div>

    <div class="panel px-4 py-6 text-sm" x-show="!loading && error !== ''" style="background:rgba(251,113,133,0.06);border-color:rgba(251,113,133,0.3);color:var(--rose);" x-text="error"></div>

    <div x-show="!loading && error === ''" class="space-y-4">
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="panel px-4 py-3">
                <div class="text-[11px] uppercase tracking-wider text-zinc-500">Pending items</div>
                <div class="mono mt-1 text-2xl font-semibold" style="color:var(--success);" x-text="pendingTotal()"></div>
            </div>
            <div class="panel px-4 py-3">
                <div class="text-[11px] uppercase tracking-wider text-zinc-500">Busiest action</div>
                <div class="mono mt-1 text-2xl font-semibold" style="color:var(--warn);" x-text="busiestAction().key"></div>
                <div class="mt-0.5 text-xs text-zinc-500" x-text="busiestAction().count + ' waiting'"></div>
            </div>
            <div class="panel px-4 py-3">
                <div class="text-[11px] uppercase tracking-wider text-zinc-500">Categories</div>
                <div class="mono mt-1 text-2xl font-semibold" style="color:var(--info);" x-text="totals().length"></div>
            </div>
            <div class="panel px-4 py-3">
                <div class="text-[11px] uppercase tracking-wider text-zinc-500">Approved Places</div>
                <div class="mono mt-1 text-2xl font-semibold" style="color:var(--violet);" x-text="approvedTotal()"></div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-5 xl:grid-cols-5">
            <template x-for="a in actions()" :key="a.key">
                <div class="panel px-4 py-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="mono text-[11px] uppercase tracking-wider text-zinc-500" x-text="a.key"></span>
                        <span class="mono text-lg font-semibold" style="color:var(--success);" x-text="a.count"></span>
                    </div>
                    <div class="bar mt-2">
                        <div class="bar-fill" x-bind:style="'width:' + (a.count / maxAction * 100) + '%'"></div>
                    </div>
                </div>
            </template>
        </div>

        <div class="panel">
            <div class="modal-head">
                <span class="text-sm font-semibold text-white">Bangued Category Infrastructure</span>
                <span class="mono text-xs text-zinc-500" x-text="totals().length + ' active categories &middot; ' + approvedTotal() + ' total places'"></span>
            </div>
            <div class="table-wrap">
                <table class="dense-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Approved in Bangued</th>
                            <th>Pending Queue</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="t in totals()" :key="t.category_id">
                            <tr>
                                <td class="font-medium text-zinc-100">
                                    <span class="flex items-center gap-2.5">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-white/10" x-bind:style="'background:' + categoryColor(t.category_id) + '22; color:' + categoryColor(t.category_id)">
                                            <i class="ph-fill text-sm" x-bind:class="categoryIcon(t.category_id)"></i>
                                        </span>
                                        <span class="font-medium" x-text="categoryLabel(t.category_id)"></span>
                                    </span>
                                </td>
                                <td>
                                    <span class="mono font-semibold text-zinc-100 text-sm" x-text="t.approved"></span>
                                    <span class="text-xs text-zinc-500 ml-1">places</span>
                                </td>
                                <td>
                                    <span class="badge" x-bind:class="t.pending > 0 ? 'badge-amber' : ''" x-text="t.pending + ' pending'"></span>
                                </td>
                                <td class="text-right">
                                    <a x-bind:href="'/admin/places?category_id=' + t.category_id" class="btn btn-xs btn-ghost text-emerald-400 hover:text-emerald-300">
                                        View places <i class="ph ph-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="modal-head">
                <span class="text-sm font-semibold text-white">Pending Moderation Heat</span>
                <span class="mono text-xs" style="color:var(--success);" x-text="'total ' + pendingTotal()"></span>
            </div>
            <div class="grid grid-cols-2 gap-2.5 p-4 sm:grid-cols-3 xl:grid-cols-5">
                <template x-for="t in totals()" :key="t.category_id">
                    <div class="rounded-lg p-3" style="border:1px solid var(--line);">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-1.5 truncate text-xs font-medium text-zinc-200">
                                <i class="ph-fill shrink-0" x-bind:class="categoryIcon(t.category_id)" x-bind:style="'color:' + categoryColor(t.category_id)"></i>
                                <span class="truncate" x-text="categoryLabel(t.category_id)"></span>
                            </span>
                            <span class="mono flex-shrink-0 text-xs" style="color:var(--success);" x-text="t.pending"></span>
                        </div>
                        <div class="bar mt-2">
                            <div class="bar-fill" x-bind:style="'width:' + (t.pending / maxPending() * 100) + '%;background:' + pendingBg(t.category_id)"></div>
                        </div>
                    </div>
                </template>
                <template x-if="!totals().length">
                    <div class="col-span-full px-4 py-8 text-center text-sm text-zinc-500">No category totals yet</div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection