@extends('admin.layout')

@section('title', 'Boundaries')
@section('subtitle', 'Barangay and municipality areas')

@section('content')
<div x-data="adminBoundaries()" x-init="init()" class="mx-auto max-w-7xl space-y-4">
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="panel xl:col-span-2">
            <div class="modal-head">
                <span class="text-sm font-semibold text-white">Boundary areas</span>
                <span class="mono text-xs text-zinc-500" x-text="items.length + ' areas'"></span>
            </div>
            <div class="table-wrap">
                <table class="dense-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>PSGC</th>
                            <th>Boundary source</th>
                            <th>Imported</th>
                            <th>Places</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr x-show="loading">
                            <td colspan="7" class="px-0 py-1">
                                <div class="flex flex-col gap-2 p-3">
                                    <div class="skel h-3.5 w-full"></div>
                                    <div class="skel h-3.5 w-3/4"></div>
                                </div>
                            </td>
                        </tr>
                        <tr x-show="!loading && error !== ''">
                            <td colspan="7" class="px-4 py-6 text-sm" style="color:var(--rose);" x-text="error"></td>
                        </tr>
                        <tr x-show="!loading && !items.length && error === ''">
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-zinc-500">No boundary areas yet</td>
                        </tr>
                        <template x-for="row in items" :key="row.id">
                            <tr>
                                <td class="mono text-zinc-500" x-text="'#' + row.id"></td>
                                <td class="font-medium text-zinc-100" x-text="row.name"></td>
                                <td>
                                    <span class="badge" x-bind:class="typeBadge(row.type)" x-text="row.type"></span>
                                </td>
                                <td class="mono text-zinc-500" x-text="row.psgc || '-'"></td>
                                <td class="mono text-xs text-zinc-400" x-text="row.boundary_source || 'n/a'"></td>
                                <td class="mono text-zinc-500" x-text="fmt(row.imported_at)"></td>
                                <td>
                                    <span class="badge badge-sky mono" x-text="(row.place_count || 0) + ' places'"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="modal-head">
                <span class="text-sm font-semibold text-white">Re-import OSM barangays</span>
            </div>
            <div class="space-y-3 p-4">
                <p class="text-xs leading-5 text-zinc-400">
                    Pulls the administrative relations for the Municipality of Bangued from OpenStreetMap through the overpass import source. Candidates arrive in the moderation queue.
                </p>
                <div>
                    <label class="field-label">Overpass query</label>
                    <pre class="max-h-48 overflow-auto rounded-lg p-3 font-mono text-[11px] leading-4 text-zinc-400" style="border:1px solid var(--line);background:var(--surface-2);" x-text="query"></pre>
                </div>
                <button type="button" class="btn btn-accent btn-sm w-full" x-on:click="reimport()" x-bind:disabled="importing">
                    <i class="ph ph-download-simple"></i>
                    <span x-text="importing ? 'Queuing...' : 'Re-import OSM barangays'"></span>
                </button>
                <p class="flex items-start gap-2 text-[11px] leading-4 text-zinc-500">
                    <i class="ph ph-seal-warning mt-0.5"></i>
                    <span>Boundary data &copy; OpenStreetMap contributors, licensed under ODbL. Attribution is recorded on each imported area.</span>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection