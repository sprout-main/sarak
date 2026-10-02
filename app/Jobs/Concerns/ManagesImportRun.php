<?php

namespace App\Jobs\Concerns;

use App\Models\ImportRun;
use Illuminate\Support\Facades\Log;
use Throwable;

trait ManagesImportRun
{
    private function ensureStarted(ImportRun $run): void
    {
        if ((string) $run->status !== 'running' || $run->started_at === null) {
            $run->forceFill(['status' => 'running', 'started_at' => now()])->save();
        }
    }

    private function importInChunks(ImportRun $run, object $imports, array $payloads, string $source): void
    {
        $chunkSize = max(1, (int) config('atlas.import_chunk_size', 250));
        $chunks = array_chunk($payloads, $chunkSize);
        $total = count($chunks);

        foreach ($chunks as $index => $chunk) {
            $imports->runImport($run->id, $chunk, $source, $this->isFinalChunk($index, $total));
            if (! $this->isFinalChunk($index, $total)) {
                $this->markChunkProgress($run, $index + 1, $total, $chunkSize);
            }
        }

        if ($total > 1) {
            $this->markChunkProgress($run, $total, $total, $chunkSize);
        }
    }

    private function isFinalChunk(int $index, int $total): bool
    {
        return $total > 0 && $index === $total - 1;
    }

    private function markChunkProgress(ImportRun $run, int $chunksDone, int $chunksTotal, int $chunkSize): void
    {
        $run->refresh();
        $run->forceFill([
            'batch_summary' => array_merge((array) ($run->batch_summary ?? []), [
                'chunks_processed' => $chunksDone,
                'chunks_total' => $chunksTotal,
                'chunk_size' => $chunkSize,
            ]),
        ])->save();
    }

    private function failRun(ImportRun $run, Throwable $exception): void
    {
        $summary = array_merge((array) ($run->batch_summary ?? []), ['error' => $exception->getMessage()]);
        $run->forceFill([
            'batch_summary' => $summary,
            'status' => 'failed',
            'finished_at' => now(),
        ])->save();

        Log::error('Import run failed', [
            'run_id' => $run->id,
            'source' => $run->source,
            'message' => $exception->getMessage(),
        ]);
    }
}