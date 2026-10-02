<?php

namespace App\Services;

use App\Models\Place;
use Illuminate\Database\Eloquent\Builder;

class GeoJsonExporter
{
    private const CRS = [
        'type' => 'name',
        'properties' => ['name' => 'urn:ogc:def:crs:OGC:1.3:CRS84'],
    ];

    public function places(array $filters = []): array
    {
        $features = [];
        $this->query($filters)->chunkById(400, function ($places) use (&$features) {
            foreach ($places as $place) {
                $features[] = $this->feature($place);
            }
        });

        return [
            'type' => 'FeatureCollection',
            'crs' => self::CRS,
            'features' => $features,
        ];
    }

    public function query(array $filters = []): Builder
    {
        $query = Place::query()
            ->approved()
            ->with(['category', 'barangay', 'hours']);

        $categorySlugs = $filters['categories'] ?? [];
        if (is_array($categorySlugs) && $categorySlugs !== []) {
            $query->inCategories(array_values(array_filter($categorySlugs, 'is_string')));
        }

        $bbox = $filters['bbox'] ?? null;
        if (is_string($bbox) && trim($bbox) !== '') {
            $parts = array_map('trim', explode(',', $bbox));
            if (count($parts) === 4) {
                $query->withinBbox(
                    (float) $parts[0],
                    (float) $parts[1],
                    (float) $parts[2],
                    (float) $parts[3],
                );
            }
        }

        return $query;
    }

    public function writeFeatures(array $filters, callable $write): void
    {
        $this->query($filters)->chunkById(400, function ($places) use ($write) {
            foreach ($places as $place) {
                $write($this->feature($place));
            }
        });
    }

    public function crs(): array
    {
        return self::CRS;
    }

    private function feature(Place $place): array
    {
        $now = $this->openNow($place);

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $place->lng, (float) $place->lat],
            ],
            'properties' => [
                'name' => $place->name,
                'slug' => $place->slug,
                'category' => $place->category?->slug,
                'category_color' => $place->category?->color,
                'address' => $place->address,
                'barangay' => $place->barangay?->name,
                'phone' => $place->phone,
                'website' => $place->website,
                'verified_tier' => $place->verified_tier,
                'source' => $place->source,
                'open_now' => $now['open_now'],
                'hours_today' => $now['hours_today'],
                'lat' => (float) $place->lat,
                'lng' => (float) $place->lng,
            ],
        ];
    }

    private function openNow(Place $place): array
    {
        $day = (int) now('Asia/Manila')->dayOfWeek;
        $today = $place->hours->first(fn ($hour) => (int) $hour->day === $day);

        if ($today === null) {
            return ['open_now' => null, 'hours_today' => null];
        }

        $opens = $this->hhmm($today->opens);
        $closes = $this->hhmm($today->closes);

        return [
            'open_now' => $today->is_closed ? false : $this->isOpenBetween($opens, $closes),
            'hours_today' => [
                'opens' => $opens,
                'closes' => $closes,
                'is_closed' => (bool) $today->is_closed,
                'note' => $today->note,
            ],
        ];
    }

    private function hhmm(mixed $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return mb_substr((string) $time, 0, 5);
    }

    private function isOpenBetween(?string $opens, ?string $closes): bool
    {
        if ($opens === null || $closes === null) {
            return false;
        }

        $current = (int) now('Asia/Manila')->format('Hi');
        $open = (int) str_replace(':', '', $opens);
        $close = (int) str_replace(':', '', $closes);

        if ($close > $open) {
            return $current >= $open && $current < $close;
        }

        return $current >= $open || $current < $close;
    }
}