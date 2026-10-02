<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RunImportRequest;
use App\Models\ImportRun;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function run(RunImportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $run = ImportRun::create([
            'source' => $validated['source'],
            'status' => ImportStatus::Running->value,
            'started_at' => now(),
        ]);

        $this->audit->record('import_run', $run->id, 'start', $request->user()?->id, [
            'source' => $validated['source'],
            'payload' => $validated['payload'] ?? null,
        ]);

        $jobClass = match ($validated['source']) {
            'overpass' => \App\Jobs\ImportOverpassJob::class,
            'nominatim' => \App\Jobs\ImportNominatimJob::class,
            default => \App\Jobs\ImportOfficialListJob::class,
        };

        if (class_exists($jobClass)) {
            dispatch(new $jobClass($run->id, $validated['payload']));
        }

        return response()->json([
            'data' => [
                'run_id' => $run->id,
                'message' => 'Import started',
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $runs = ImportRun::query()
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $runs->map(fn (ImportRun $run) => [
                'id' => $run->id,
                'source' => $run->source,
                'batch_summary' => $run->batch_summary,
                'status' => $run->status,
                'started_at' => $run->started_at?->toISOString(),
                'finished_at' => $run->finished_at?->toISOString(),
            ]),
        ]);
    }
}