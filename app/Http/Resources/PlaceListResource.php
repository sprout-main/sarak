<?php

namespace App\Http\Resources;

use App\Models\Place;
use App\Services\HoursService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PlaceListResource extends JsonResource
{
    private HoursService $hoursService;

    public function toArray(Request $request): array
    {
        /** @var Place $place */
        $place = $this->resource;

        $photos = $place->relationLoaded('photos')
            ? $place->photos
            : $place->photos()->approved()->get();

        return [
            'id' => $place->id,
            'name' => $place->name,
            'slug' => $place->slug,
            'category' => $place->category ? [
                'id' => $place->category->id,
                'slug' => $place->category->slug,
                'label' => $place->category->label,
                'icon_key' => $place->category->icon_key,
                'color' => $place->category->color,
            ] : null,
            'address' => $place->address,
            'barangay' => $place->barangay ? [
                'id' => $place->barangay->id,
                'name' => $place->barangay->name,
            ] : null,
            'lat' => $place->lat,
            'lng' => $place->lng,
            'verified_tier' => $place->verified_tier,
            'source' => $place->source,
            'phone' => $place->phone,
            'website' => $place->website,
            'open_now' => $this->hours()->openNow($place),
            'hours_today' => $this->hours()->hoursToday($place),
            'open_24h' => $this->hours()->is24h($place),
            'telemetry' => $this->hours()->telemetry($place),
            'is_claimed' => (bool) ($place->is_claimed ?? false),
            'distance_m' => $place->distance_m !== null ? round((float) $place->distance_m, 1) : null,
            'photo_count' => (int) ($place->photo_count ?? $photos->count()),
            'cover_photo' => $photos->first()?->path,
        ];
    }

    protected function hoursFor(Place $place): Collection
    {
        return $place->relationLoaded('hours')
            ? $place->hours
            : $place->hours()->get();
    }

    protected static function hm(string|\DateTimeInterface|null $time): ?string
    {
        if ($time === null) {
            return null;
        }

        $value = (string) $time;

        if ($value === '' || strlen($value) < 5) {
            return null;
        }

        return substr($value, 0, 5);
    }

    private function hours(): HoursService
    {
        return $this->hoursService ??= app(HoursService::class);
    }
}