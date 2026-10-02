<?php

namespace App\Console\Commands;

use App\Enums\ImportStatus;
use App\Jobs\ImportNominatimJob;
use App\Jobs\ImportOfficialListJob;
use App\Jobs\ImportOverpassJob;
use App\Models\ImportRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Throwable;

class AtlasImportCommand extends Command
{
    protected $signature = 'sarak:import {source?} {payload?}';

    protected $aliases = ['atlas:import'];

    protected $description = 'Run a Sarak import synchronously (overpass|official_list|nominatim).';

    public function handle(): int
    {
        $source = (string) ($this->argument('source') ?? 'overpass');
        $payload = (string) ($this->argument('payload') ?? '');

        if ($source === 'overpass' && $payload === '') {
            $payload = 'auto';
        }

        $jobClass = match ($source) {
            'overpass' => ImportOverpassJob::class,
            'official_list' => ImportOfficialListJob::class,
            'nominatim' => ImportNominatimJob::class,
            default => null,
        };

        if ($jobClass === null) {
            $this->error('Unknown import source "' . $source . '". Use overpass, official_list or nominatim.');

            return self::FAILURE;
        }

        $run = ImportRun::create([
            'source' => $source,
            'status' => ImportStatus::Running->value,
            'started_at' => now(),
        ]);

        try {
            Bus::dispatchSync(new $jobClass($run->id, $payload));
            $run->refresh();
            $summary = is_array($run->batch_summary) ? $run->batch_summary : [];
            $succeeded = (string) $run->status === ImportStatus::Done->value;

            $line = sprintf(
                'Import [%s] run #%d %s: attempted %d, queued %d, skipped_duplicates %d%s',
                $source,
                $run->id,
                (string) $run->status,
                $summary['attempted'] ?? 0,
                $summary['queued'] ?? 0,
                $summary['skipped_duplicates'] ?? 0,
                isset($summary['error']) ? ' (error: ' . $summary['error'] . ')' : '',
            );

            if ($succeeded) {
                $this->info($line);
            } else {
                $this->error($line);
            }

            return $succeeded ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $run->forceFill([
                'status' => ImportStatus::Failed->value,
                'finished_at' => now(),
                'batch_summary' => array_merge((array) ($run->batch_summary ?? []), ['error' => $e->getMessage()]),
            ])->save();
            $this->error('Import failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}