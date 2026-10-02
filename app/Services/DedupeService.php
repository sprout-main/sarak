<?php

namespace App\Services;

use App\Models\ModQueue;
use App\Models\Place;
use App\Support\Text;

class DedupeService
{
    private const DEFAULT_MAX_METERS = 25;

    public function __construct(
        private readonly GeoSpatial $spatial,
    ) {
    }

    public function isDuplicateApproved(
        string $name,
        float $lat,
        float $lng,
        float $maxMeters = self::DEFAULT_MAX_METERS,
        ?string $phone = null,
    ): ?int {
        $candidates = Place::query()
            ->approved()
            ->withinRadius($lat, $lng, $this->candidateRadius($maxMeters))
            ->get(['id', 'name', 'phone', 'lat', 'lng']);

        foreach ($candidates as $candidate) {
            if ($this->matches(
                $name,
                $lat,
                $lng,
                $maxMeters,
                $phone,
                (string) $candidate->name,
                (float) $candidate->lat,
                (float) $candidate->lng,
                (string) ($candidate->phone ?? ''),
            )) {
                return (int) $candidate->id;
            }
        }

        return null;
    }

    public function checkAgainstQueue(array $payload): bool
    {
        $lat = isset($payload['lat']) ? (float) $payload['lat'] : 0.0;
        $lng = isset($payload['lng']) ? (float) $payload['lng'] : 0.0;
        $name = isset($payload['name']) ? (string) $payload['name'] : '';
        $phone = isset($payload['phone']) ? (string) $payload['phone'] : '';

        $pending = ModQueue::query()
            ->where('action', 'create')
            ->where('status', 'pending')
            ->whereNotNull('payload')
            ->get(['id', 'payload']);

        foreach ($pending as $row) {
            $queued = $row->payload;
            if (! is_array($queued)) {
                continue;
            }

            if ($this->matches(
                $name,
                $lat,
                $lng,
                self::DEFAULT_MAX_METERS,
                $phone,
                (string) ($queued['name'] ?? ''),
                isset($queued['lat']) ? (float) $queued['lat'] : 0.0,
                isset($queued['lng']) ? (float) $queued['lng'] : 0.0,
                isset($queued['phone']) ? (string) $queued['phone'] : '',
            )) {
                return true;
            }
        }

        return false;
    }

    private function matches(
        string $name,
        float $lat,
        float $lng,
        float $maxMeters,
        ?string $phone,
        string $candidateName,
        float $candidateLat,
        float $candidateLng,
        string $candidatePhone,
    ): bool {
        $distance = $this->spatial->haversineM($lat, $lng, $candidateLat, $candidateLng);

        if ($distance <= (float) config('atlas.dedupe_fuzzy_meters', 150)
            && Text::namesSimilar($name, $candidateName)) {
            return true;
        }

        if ($distance <= $maxMeters
            && Text::normalize($name) === Text::normalize($candidateName)) {
            return true;
        }

        if ($distance <= (float) config('atlas.dedupe_phone_meters', 2000)
            && $this->phoneMatch($phone, $candidatePhone)) {
            return true;
        }

        return false;
    }

    private function phoneMatch(?string $left, ?string $right): bool
    {
        $normalizedLeft = $this->normalizePhone($left ?? '');
        $normalizedRight = $this->normalizePhone($right ?? '');

        if ($normalizedLeft === '' || $normalizedRight === '') {
            return false;
        }

        if (strlen($normalizedLeft) < 7 || strlen($normalizedRight) < 7) {
            return false;
        }

        return $normalizedLeft === $normalizedRight;
    }

    private function normalizePhone(string $value): string
    {
        return (string) preg_replace('/\D+/u', '', $value);
    }

    private function candidateRadius(float $maxMeters): float
    {
        $fuzzy = (float) config('atlas.dedupe_fuzzy_meters', 150);
        $phone = (float) config('atlas.dedupe_phone_meters', 2000);

        return max($maxMeters, $fuzzy, $phone);
    }
}