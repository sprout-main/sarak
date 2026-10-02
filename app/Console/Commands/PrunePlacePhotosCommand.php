<?php

namespace App\Console\Commands;

use App\Enums\PhotoStatus;
use App\Enums\QueueAction;
use App\Models\ModQueue;
use App\Models\Place;
use App\Models\PlacePhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PrunePlacePhotosCommand extends Command
{
    protected $signature = 'sarak:photos:prune
                            {--disk=public : Filesystem disk that holds the photo tree}
                            {--status= : Treat only this moderation status as used}
                            {--quarantine : Move candidates into storage/app/quarantine}
                            {--purge : Permanently delete existing quarantine directories}
                            {--dry-run : Report only, never move or delete}';

    protected $aliases = ['atlas:photos:prune'];

    protected $description = 'Remove place photo files that no place_photos row references.';

    public function handle(): int
    {
        $disk = (string) $this->option('disk');

        if (config('filesystems.disks.'.$disk) === null) {
            $this->error('Unknown filesystem disk "'.$disk.'".');

            return self::FAILURE;
        }

        if ($this->option('purge')) {
            return $this->purge();
        }

        $storage = Storage::disk($disk);
        $root = rtrim((string) config('filesystems.disks.'.$disk.'.root'), '/\\');

        $this->line('disk   : '.$disk);
        $this->line('root   : '.$root);

        if (! $storage->exists('photos')) {
            $this->error('No photos directory under that root. Check FILESYSTEM_PUBLIC_ROOT before proceeding.');

            return self::FAILURE;
        }

        $status = $this->option('status');
        $status = is_string($status) && $status !== '' ? $status : null;

        if ($status !== null && PhotoStatus::tryFrom($status) === null) {
            $this->error('Unknown photo status "'.$status.'". Use pending, approved or rejected.');

            return self::FAILURE;
        }

        $used = $this->usedPaths($status);
        $scan = $this->scan($storage, $used);
        $candidates = $scan['candidates'];
        $groups = $this->group($candidates);

        $this->render($disk, $root, $status, $used, $scan, $groups);

        if ($candidates === []) {
            return self::SUCCESS;
        }

        if (! $this->option('quarantine') || $this->option('dry-run')) {
            $this->newLine();
            $this->line('Nothing moved. Re-run with --quarantine to move the candidates.');

            return self::SUCCESS;
        }

        return $this->quarantine($storage, $candidates, $groups);
    }

    private function usedPaths(?string $status): array
    {
        $used = [];

        $photos = PlacePhoto::query()->orderBy('id');

        if ($status !== null) {
            $photos->where('status', $status);
        }

        foreach ($photos->pluck('path') as $path) {
            $used[$this->normalize((string) $path)] = true;
        }

        $queue = ModQueue::query()->where('action', QueueAction::Photo->value);

        if ($status !== null) {
            $queue->where('status', $status);
        }

        foreach ($queue->cursor() as $item) {
            $payload = $item->payload;
            $path = is_array($payload) ? ($payload['photo_path'] ?? null) : null;

            if (is_string($path) && $path !== '') {
                $used[$this->normalize($path)] = true;
            }
        }

        return $used;
    }

    private function scan($storage, array $used): array
    {
        $candidates = [];
        $diskFiles = 0;
        $diskBytes = 0;

        foreach ($storage->allFiles('photos') as $file) {
            $rel = $this->normalize($file);
            $size = (int) $storage->size($file);

            $diskFiles++;
            $diskBytes += $size;

            if (isset($used[$rel])) {
                continue;
            }

            $candidates[$rel] = $size;
        }

        ksort($candidates);

        return compact('candidates', 'diskFiles', 'diskBytes');
    }

    private function group(array $candidates): array
    {
        $ids = [];

        foreach (array_keys($candidates) as $rel) {
            $id = $this->placeId($rel);

            if ($id !== null) {
                $ids[$id] = true;
            }
        }

        $places = Place::withTrashed()
            ->whereIn('id', array_keys($ids))
            ->get(['id', 'status', 'deleted_at'])
            ->keyBy('id');

        $count = array_fill_keys(['on-map', 'off-map', 'place-missing', 'unknown-layout'], 0);
        $bytes = array_fill_keys(['on-map', 'off-map', 'place-missing', 'unknown-layout'], 0);

        foreach ($candidates as $rel => $size) {
            $key = $this->classify($rel, $places);
            $count[$key]++;
            $bytes[$key] += $size;
        }

        return ['count' => $count, 'bytes' => $bytes, 'places' => $places];
    }

    private function render(string $disk, string $root, ?string $status, array $used, array $scan, array $groups): void
    {
        $candidates = $scan['candidates'];
        $totalBytes = array_sum($groups['bytes']);
        $places = $groups['places'];

        $this->newLine();
        $this->line(str_repeat('-', 68));
        $this->line(sprintf('  status filter              : %s', $status ?? 'none (every status kept)'));
        $this->line(sprintf('  referenced paths (keep)    : %d', count($used)));
        $this->line(sprintf('  photo files on disk        : %d  (%.2f MB)', $scan['diskFiles'], $scan['diskBytes'] / 1048576));
        $this->line(sprintf('  candidates (unreferenced)  : %d  (%.2f MB)', count($candidates), $totalBytes / 1048576));
        $this->line(str_repeat('-', 68));

        foreach (['on-map', 'off-map', 'place-missing', 'unknown-layout'] as $bucket) {
            if ($groups['count'][$bucket] === 0) {
                continue;
            }

            $this->line(sprintf(
                '  %-24s %6d  %10.2f MB',
                $bucket,
                $groups['count'][$bucket],
                $groups['bytes'][$bucket] / 1048576,
            ));
        }

        $this->line(str_repeat('-', 68));

        if ($candidates === []) {
            $this->info('No unreferenced photo files.');

            return;
        }

        $manifest = storage_path('logs/photos-prune-'.now()->format('Ymd-His').'.txt');
        $lines = [
            sprintf('# disk=%s root=%s status=%s', $disk, $root, $status ?? 'none'),
            sprintf('# keep=%d disk_files=%d candidates=%d bytes=%d', count($used), $scan['diskFiles'], count($candidates), $totalBytes),
        ];

        foreach ($candidates as $rel => $size) {
            $id = $this->placeId($rel);
            $place = $id === null ? null : $places->get($id);

            $lines[] = implode("\t", [
                $rel,
                (string) $size,
                $this->classify($rel, $places),
                $place === null ? '' : (string) $place->name,
            ]);
        }

        file_put_contents($manifest, implode(PHP_EOL, $lines).PHP_EOL);
        $this->line('  manifest: '.$manifest);

        $sample = array_slice(array_keys($candidates), 0, 20, true);

        foreach ($sample as $rel) {
            $this->line(sprintf('    %-44s %10.2f MB', $rel, $candidates[$rel] / 1048576));
        }

        if (count($candidates) > count($sample)) {
            $this->line(sprintf('    ... and %d more', count($candidates) - count($sample)));
        }
    }

    private function quarantine($storage, array $candidates, array $groups): int
    {
        $prefix = 'quarantine/photos-'.now()->format('Ymd-His');
        $local = Storage::disk('local');

        $moved = 0;
        $failed = 0;
        $bytes = 0;
        $problems = [];

        $bar = $this->output->createProgressBar(count($candidates));
        $bar->start();

        foreach ($candidates as $rel => $size) {
            try {
                $target = $local->path($prefix.'/'.$rel);
                $this->ensureDirectory(dirname($target));
                $this->relocate($storage->path($rel), $target);

                $moved++;
                $bytes += (int) $size;
            } catch (Throwable $e) {
                $failed++;

                if (count($problems) < 25) {
                    $problems[] = $rel.': '.$e->getMessage();
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $emptied = $this->pruneEmptyDirectories($storage);

        $this->newLine(2);
        $this->line(str_repeat('-', 68));
        $this->line(sprintf('  moved                     : %d  (%.2f MB)', $moved, $bytes / 1048576));
        $this->line(sprintf('  failed                    : %d', $failed));
        $this->line(sprintf('  empty dirs removed        : %d', $emptied));
        $this->line(sprintf('  on-map orphans moved      : %d', $groups['count']['on-map']));
        $this->line(str_repeat('-', 68));

        foreach ($problems as $problem) {
            $this->error('  '.$problem);
        }

        if ($failed > 0) {
            return self::FAILURE;
        }

        $this->line('quarantine: '.$local->path($prefix));
        $this->line('Reclaim the space with: php artisan sarak:photos:prune --purge');

        return self::SUCCESS;
    }

    private function purge(): int
    {
        $local = Storage::disk('local');

        $dirs = array_values(array_filter(
            $local->directories('quarantine'),
            fn (string $dir) => (bool) preg_match('#^quarantine/photos-\d{8}-\d{6}$#', $dir),
        ));

        if ($dirs === []) {
            $this->line('No quarantine directories found.');

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($dirs as $dir) {
            $files = $local->allFiles($dir);
            $bytes = 0;

            foreach ($files as $file) {
                $bytes += (int) $local->size($file);
            }

            $total += $bytes;
            $this->line(sprintf('  %-34s %6d files  %10.2f MB', $dir, count($files), $bytes / 1048576));
        }

        $this->newLine();
        $this->line(sprintf('reclaimable: %s bytes (%.2f MB)', number_format($total), $total / 1048576));

        if (! $this->confirm('Permanently delete these directories?', true)) {
            $this->line('Aborted. Nothing deleted.');

            return self::SUCCESS;
        }

        foreach ($dirs as $dir) {
            $local->deleteDirectory($dir);
        }

        $this->info('Purged '.count($dirs).' quarantine director'.(count($dirs) === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }

    private function pruneEmptyDirectories($storage): int
    {
        $dirs = $storage->allDirectories('photos');
        rsort($dirs);

        $removed = 0;

        foreach ($dirs as $dir) {
            if ($storage->allFiles($dir) === []) {
                $storage->deleteDirectory($dir);
                $removed++;
            }
        }

        return $removed;
    }

    private function relocate(string $from, string $to): void
    {
        if (@rename($from, $to)) {
            return;
        }

        if (! @copy($from, $to)) {
            throw new RuntimeException('copy failed');
        }

        if (! @unlink($from)) {
            @unlink($to);

            throw new RuntimeException('unlink failed');
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('cannot create '.$dir);
        }
    }

    private function classify(string $rel, $places): string
    {
        $id = $this->placeId($rel);

        if ($id === null) {
            return 'unknown-layout';
        }

        $place = $places->get($id);

        if ($place === null) {
            return 'place-missing';
        }

        return $place->status === 'approved' && $place->deleted_at === null ? 'on-map' : 'off-map';
    }

    private function placeId(string $rel): ?int
    {
        return preg_match('#^photos/(\d+)/#', $rel, $matches) === 1 ? (int) $matches[1] : null;
    }

    private function normalize(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }
}
