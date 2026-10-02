<?php

namespace App\Services;

use App\Models\Place;
use Generator;

class StreamingGeoJsonExporter
{
    public function features(array $filters = []): Generator
    {
        $query = Place::query()
            ->approved()
            ->select([
                'id',
                'name',
                'slug',
                'category_id',
                'address',
                'barangay_id',
                'lat',
                'lng',
                'verified_tier',
                'source',
                'phone',
                'website',
            ])
            ->with(['category', 'barangay', 'hours'])
            ->orderBy('id');

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

        $lastId = 0;

        while (true) {
            $places = (clone $query)
                ->where('id', '>', $lastId)
                ->limit(500)
                ->orderBy('id')
                ->get();

            if ($places->isEmpty()) {
                break;
            }

            foreach ($places as $place) {
                yield $this->feature($place);
            }

            $lastId = $places->last()->getKey();
        }
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