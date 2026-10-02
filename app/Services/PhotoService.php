<?php

namespace App\Services;

use App\Models\PlacePhoto;
use Illuminate\Support\Facades\DB;

/**
 * Row plus file lifecycle for place photos.
 *
 * The row is always removed first: if the file unlink then fails we are left
 * with an unreferenced file, which sarak:photos:prune can reclaim, rather than
 * with a row pointing at nothing, which would render as a broken image on the
 * map with no obvious owner.
 */
class PhotoService
{
    public function __construct(
        private readonly PhotoStorage $storage,
        private readonly AtlasCache $atlas,
    ) {}

    public function delete(PlacePhoto $photo): void
    {
        $placeId = (int) $photo->place_id;
        $path = (string) $photo->path;

        DB::transaction(static function () use ($photo): void {
            $photo->delete();
        });

        $this->storage->forgetForPlace($placeId, [$path]);
        $this->atlas->invalidate();
    }

    /**
     * Removes every photo row for a place along with its files. Used by the
     * place delete path, where the rows would otherwise be removed by the
     * MySQL cascade while the files survived.
     */
    public function deleteForPlace(int $placeId): int
    {
        $paths = PlacePhoto::where('place_id', $placeId)->pluck('path')->all();

        PlacePhoto::where('place_id', $placeId)->delete();

        $deleted = $this->storage->forgetForPlace($placeId, $paths);

        if ($deleted > 0) {
            $this->atlas->invalidate();
        }

        return $deleted;
    }

    /**
     * Exactly one photo per place carries the cover flag. Approving a photo
     * used to set is_cover unconditionally, which left several rows flagged and
     * made cover_photo resolve on an unspecified tie.
     */
    public function setCover(PlacePhoto $photo): void
    {
        DB::transaction(function () use ($photo): void {
            PlacePhoto::where('place_id', $photo->place_id)
                ->whereKeyNot($photo->getKey())
                ->update(['is_cover' => false]);

            if (! $photo->is_cover) {
                $photo->update(['is_cover' => true]);
            }
        });

        $this->atlas->invalidate();
    }

    /**
     * Promotes $photo only if nothing else already holds the cover, so an
     * approval never silently displaces a curated cover.
     */
    public function ensureCover(?PlacePhoto $photo): void
    {
        if ($photo === null) {
            return;
        }

        $covered = PlacePhoto::where('place_id', $photo->place_id)
            ->whereKeyNot($photo->getKey())
            ->where('is_cover', true)
            ->exists();

        if (! $covered) {
            $this->setCover($photo);
        }
    }
}
