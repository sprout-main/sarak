<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Removes files from the public photo tree.
 *
 * place_photos.place_id is declared cascadeOnDelete, which is enforced by
 * MySQL rather than by Eloquent. Deleting a place therefore drops its photo
 * rows without ever firing a model event, so every call site has to remove
 * the files itself. Routing all of them through here keeps that in one place
 * and gives us a single place to refuse paths that would escape photos/.
 */
class PhotoStorage
{
    private const ROOT = 'photos';

    public function disk(): Filesystem
    {
        return Storage::disk('public');
    }

    public function forget(string $relativePath): bool
    {
        $safe = $this->normalize($relativePath);

        if ($safe === null) {
            return false;
        }

        return $this->disk()->delete($safe);
    }

    /**
     * @param  iterable<int, string|null>  $relativePaths
     */
    public function forgetMany(iterable $relativePaths): int
    {
        $deleted = 0;

        foreach ($relativePaths as $relativePath) {
            if (is_string($relativePath) && $this->forget($relativePath)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Deletes every supplied path, then removes photos/<placeId>/ if it ended
     * up empty. The directory is left alone if anything else is still in it.
     *
     * @param  iterable<int, string|null>  $relativePaths
     */
    public function forgetForPlace(int $placeId, iterable $relativePaths): int
    {
        $deleted = $this->forgetMany($relativePaths);

        $this->prunePlaceDir($placeId);

        return $deleted;
    }

    public function prunePlaceDir(int $placeId): bool
    {
        if ($placeId <= 0) {
            return false;
        }

        $dir = self::ROOT.'/'.$placeId;
        $disk = $this->disk();

        if (! $disk->exists($dir)) {
            return false;
        }

        foreach ($disk->files($dir) as $file) {
            if (! $disk->delete($file)) {
                return false;
            }
        }

        return $disk->deleteDirectory($dir);
    }

    /**
     * Removes photos/<placeId>/ for each id that has no remaining files. Used
     * after a bulk reseed where every photo row is gone.
     *
     * @param  array<int, int|string>  $placeIds
     */
    public function prunePlaceDirs(array $placeIds): int
    {
        $removed = 0;

        foreach (array_unique(array_map('intval', $placeIds)) as $placeId) {
            if ($this->prunePlaceDir($placeId)) {
                $removed++;
            }
        }

        return $removed;
    }

    public function exists(string $relativePath): bool
    {
        $safe = $this->normalize($relativePath);

        return $safe !== null && $this->disk()->exists($safe);
    }

    /**
     * Returns the disk-relative path, or null when the input is unusable.
     * Absolute paths, drive letters, traversal segments and anything outside
     * photos/ are rejected rather than clamped.
     */
    public function normalize(string $relativePath): ?string
    {
        $path = trim(str_replace('\\', '/', $relativePath));

        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }

        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:#', $path) === 1) {
            return null;
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                return null;
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return null;
        }

        $normalized = implode('/', $segments);

        if (! str_starts_with($normalized, self::ROOT.'/')) {
            return null;
        }

        return $normalized;
    }
}
