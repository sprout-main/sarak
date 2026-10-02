<?php

namespace App\Services;

use App\Enums\PlaceStatus;
use App\Enums\Source;
use App\Enums\VerificationTier;
use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlaceService
{
    public function __construct(
        private readonly BarangayResolver $barangays,
    ) {
    }

    public function resolveBarangayId(float $lat, float $lng): ?int
    {
        return $this->barangays->resolveId($lat, $lng);
    }

    public function slugify(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'place';
        }

        $slug = $base;

        while (Place::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.substr(Str::random(10), -5);
        }

        return $slug;
    }

    public function writeGeometry(float $lat, float $lng): Expression
    {
        $lat = is_nan($lat) ? 0.0 : $lat;
        $lng = is_nan($lng) ? 0.0 : $lng;
        $lat = max(-90.0, min(90.0, $lat));
        $lng = max(-180.0, min(180.0, $lng));

        return DB::raw("ST_GeomFromText('POINT({$lng} {$lat})', 4326)");
    }

    public function applyLocation(Place $place, float $lat, float $lng, ?int $barangayId = null): void
    {
        $place->forceFill([
            'lat' => $lat,
            'lng' => $lng,
            'geom' => $this->writeGeometry($lat, $lng),
        ]);

        if ($barangayId !== null) {
            $place->barangay_id = $barangayId;
        } else {
            $place->barangay_id = $this->resolveBarangayId($lat, $lng);
        }

        $place->save();
    }

    public function createDirect(array $validated, User $user): Place
    {
        return DB::transaction(function () use ($validated, $user) {
            $slug = $this->slugify($validated['name']);

            $place = new Place();
            $place->name = $validated['name'];
            $place->slug = $slug;
            $place->category_id = $validated['category_id'];
            $place->description = $validated['description'] ?? null;
            $place->address = $validated['address'] ?? null;
            $place->phone = $validated['phone'] ?? null;
            $place->website = $validated['website'] ?? null;
            $place->source = Source::Manual->value;
            $place->status = PlaceStatus::Approved->value;
            $place->verified_tier = VerificationTier::Verified->value;
            $place->submitted_by = $user->id;
            $place->approved_by = $user->id;
            $place->approved_at = now();

            $this->applyLocation(
                $place,
                (float) $validated['lat'],
                (float) $validated['lng'],
                isset($validated['barangay_id']) && $validated['barangay_id'] ? (int) $validated['barangay_id'] : null,
            );

            if (isset($validated['hours']) && is_array($validated['hours'])) {
                $this->syncHours($place, $validated['hours']);
            }

            return $place;
        });
    }

    public function createFromQueue(array $payload, array $extra = []): Place
    {
        $lat = isset($payload['lat']) && is_numeric($payload['lat']) ? (float) $payload['lat'] : 0.0;
        $lng = isset($payload['lng']) && is_numeric($payload['lng']) ? (float) $payload['lng'] : 0.0;
        $name = (string) ($payload['name'] ?? '');

        $place = Place::create(array_merge([
            'category_id' => $payload['category_id'] ?? null,
            'name' => $name,
            'slug' => $this->slugify($name),
            'description' => $payload['description'] ?? null,
            'address' => $payload['address'] ?? null,
            'barangay_id' => $payload['barangay_id'] ?? $this->resolveBarangayId($lat, $lng),
            'geom' => $this->writeGeometry($lat, $lng),
            'lat' => $lat,
            'lng' => $lng,
            'source' => $payload['source'] ?? Source::Manual->value,
            'source_ref' => $payload['source_ref'] ?? null,
            'source_url' => $payload['source_url'] ?? null,
            'status' => PlaceStatus::Approved->value,
            'verified_tier' => VerificationTier::Community->value,
            'submitted_by' => $payload['submitted_by'] ?? null,
        ], $extra));

        if (isset($payload['hours']) && is_array($payload['hours'])) {
            $this->syncHours($place, $payload['hours']);
        }

        return $place;
    }

    public function syncHours(Place $place, array $rows): void
    {
        $place->hours()->delete();

        foreach ($rows as $row) {
            if (! isset($row['day'])) {
                continue;
            }

            $place->hours()->create([
                'day' => (int) $row['day'],
                'opens' => ! empty($row['opens']) ? (string) $row['opens'] : null,
                'closes' => ! empty($row['closes']) ? (string) $row['closes'] : null,
                'is_closed' => $this->boolish($row['is_closed'] ?? false),
                'note' => $row['note'] ?? null,
            ]);
        }
    }

    private function boolish(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }
}