@extends('admin.layout')

@section('title', 'Import')
@section('subtitle', 'Park candidates into the moderation queue')

@section('content')
<div x-data="adminImport()" x-init="init()" class="mx-auto max-w-7xl space-y-4">
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="panel xl:col-span-2">
            <div class="modal-head">
                <span class="text-sm font-semibold text-white">New import</span>
                <span class="badge badge-zinc font-mono uppercase">queue pickers only</span>
            </div>
            <div class="space-y-4 p-4">
                <div>
                    <label class="field-label">Source</label>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        <template x-for="s in sources" :key="s.id">
                            <button type="button"
                                x-on:click="source = s.id"
                                class="rounded-lg border px-3 py-2.5 text-left transition-opacity hover:opacity-90"
                                x-bind:class="source === s.id ? 'bg-[#4ade80]/5' : ''"
                                x-bind:style="source === s.id ? 'border-color:rgba(74,222,128,0.6);' : ''">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-zinc-100" x-text="s.label"></span>
                                    <span class="h-2.5 w-2.5 rounded-full" x-bind:style="source === s.id ? 'background:#4ade80;' : 'background:#3f3f46;'"></span>
                                </div>
                                <div class="mt-1 text-[11px] text-zinc-500" x-text="s.hint"></div>
                            </button>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="field-label">Payload</label>
                    <textarea class="input min-h-[160px] mono text-xs" x-model="payload" placeholder="Paste an Overpass QL query, GeoJSON, or CSV here"></textarea>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <label class="btn btn-ghost btn-sm cursor-pointer"><i class="ph ph-file"></i> Load file
                        <input type="file" class="hidden" accept=".csv,.geojson,.json,.txt,text/csv,application/json,text/plain" x-on:change="onFile($event)">
                    </label>
                    <span class="text-xs text-zinc-500">or paste the text directly</span>
                    <button type="button" class="btn btn-accent btn-sm ml-auto" x-on:click="run()" x-bind:disabled="running || !payload.trim()">
                        <i class="ph ph-play"></i> <span x-text="running ? 'Queuing...' : 'Run import'"></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="modal-head">
                <span class="text-sm font-semibold text-white">How it works</span>
            </div>
            <div class="space-y-2.5 p-4 text-xs leading-5 text-zinc-400">
                <p>Imports never write places directly. Every candidate lands as a create action in the moderation queue, tagged with its source and a source reference.</p>
                <p>Review and approve them from the queue page. This widget keeps watching the pending queue and surfaces newly parked candidates here.</p>
                <p class="text-zinc-500">The exact import grammar is defined by the backend ImportService per source.</p>
            </div>
        </div>
    </div>

    <div class="panel px-4 py-3" x-show="runId !== null">
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <span class="badge badge-green">Run <span class="mono" x-text="runId"></span></span>
            <span class="text-zinc-400">Queued. Watching the pending queue for parked candidates...</span>
            <button type="button" class="btn btn-xs btn-ghost ml-auto" x-on:click="stopPolling()">Stop watching</button>
        </div>
    </div>

    <div class="panel" x-show="candidates.length">
        <div class="modal-head">
            <span class="text-sm font-semibold text-white">Newly parked candidates</span>
            <span class="mono text-xs" style="color:var(--success);" x-text="candidates.length + ' in queue'"></span>
        </div>
        <div class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
            <template x-for="c in candidates" :key="c.id">
                <a href="/admin/queue" class="rounded-lg border border-white/10 p-3 transition-opacity hover:opacity-90">
                    <div class="flex items-center justify-between gap-2">
                        <span class="badge badge-sky" x-text="c.action"></span>
                        <span class="mono text-[11px]" style="color:var(--success);" x-text="'#' + c.id"></span>
                    </div>
                    <div class="mt-2 truncate text-sm font-medium text-zinc-100" x-text="c.place ? c.place.name : '(new place)'"></div>
                    <div class="mono mt-1 text-[11px] text-zinc-500">Review in queue</div>
                </a>
            </template>
        </div>
    </div>

    <div class="panel">
        <div class="modal-head">
            <span class="text-sm font-semibold text-white">Import history</span>
            <span class="mono text-xs text-zinc-500" x-text="history.length + ' runs'"></span>
        </div>
        <div class="table-wrap">
            <table class="dense-table">
                <thead>
                    <tr>
                        <th>Run</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th>Finished</th>
                        <th>Batch summary</th>
                    </tr>
                </thead>
                <tbody>
                    <tr x-show="loadingHistory">
                        <td colspan="6" class="px-0 py-1">
                            <div class="flex flex-col gap-2 p-3">
                                <div class="skel h-3.5 w-full"></div>
                                <div class="skel h-3.5 w-2/3"></div>
                            </div>
                        </td>
                    </tr>
                    <tr x-show="!loadingHistory && historyError !== ''">
                        <td colspan="6" class="px-4 py-6 text-sm" style="color:var(--rose);" x-text="historyError"></td>
                    </tr>
                    <tr x-show="!loadingHistory && !history.length && historyError === ''">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-zinc-500">No imports yet</td>
                    </tr>
                    <template x-for="run in history" :key="run.id">
                        <tr>
                            <td class="mono text-zinc-500" x-text="'#' + run.id"></td>
                            <td>
                                <span class="badge" x-bind:class="srcBadge(run.source)" x-text="run.source"></span>
                            </td>
                            <td>
                                <span class="badge" x-bind:class="runStatusBadge(run.status)" x-text="run.status"></span>
                            </td>
                            <td class="mono text-zinc-500" x-text="fmt(run.started_at)"></td>
                            <td class="mono text-zinc-500" x-text="fmt(run.finished_at)"></td>
                            <td>
                                <template x-for="row in summaryRows(run)" :key="row.key">
                                    <div class="flex gap-1.5 text-[11px]">
                                        <span class="mono flex-shrink-0 text-zinc-500" x-text="row.key"></span>
                                        <span class="text-zinc-300" x-text="row.value"></span>
                                    </div>
                                </template>
                                <template x-if="!run.batch_summary">
                                    <span class="text-zinc-600">pending</span>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection