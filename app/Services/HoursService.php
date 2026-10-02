<?php

namespace App\Services;

use App\Models\Place;
use App\Models\PlaceHour;
use Illuminate\Support\Collection;

class HoursService
{
    public function forDay(Place $place, int $day): ?PlaceHour
    {
        return $this->hoursFor($place)
            ->first(fn (PlaceHour $hour) => (int) $hour->day === $day);
    }

    public function hasHours(Place $place): bool
    {
        return $this->hoursFor($place)->isNotEmpty();
    }

    public function is24h(Place $place): bool
    {
        foreach ($this->hoursFor($place) as $hour) {
            $opens = $this->hm($hour->opens);
            $closes = $this->hm($hour->closes);

            if ($opens === null || $closes === null) {
                continue;
            }

            if ($opens === $closes) {
                return true;
            }

            if ($opens === '00:00' && $closes === '23:59') {
                return true;
            }
        }

        return false;
    }

    public function openNow(?Place $place): ?bool
    {
        if ($place === null) {
            return null;
        }

        $now = now('Asia/Manila');
        $row = $this->forDay($place, (int) $now->dayOfWeek);

        if ($row === null) {
            return null;
        }

        if ($row->is_closed) {
            return false;
        }

        $opens = $this->hm($row->opens);
        $closes = $this->hm($row->closes);

        if ($opens === null || $closes === null) {
            return null;
        }

        if ($opens === $closes) {
            return true;
        }

        return $this->withinWindow($opens, $closes, $now->format('H:i'));
    }

    public function hoursToday(?Place $place): ?array
    {
        if ($place === null) {
            return null;
        }

        $row = $this->forDay($place, (int) now('Asia/Manila')->dayOfWeek);

        if ($row === null) {
            return null;
        }

        $opens = $this->hm($row->opens);
        $closes = $this->hm($row->closes);

        if ($row->is_closed || ($opens === null && $closes === null)) {
            return [
                'opens' => null,
                'closes' => null,
                'is_closed' => true,
                'note' => $row->note,
            ];
        }

        return [
            'opens' => $opens,
            'closes' => $closes,
            'is_closed' => (bool) $row->is_closed,
            'note' => $row->note,
        ];
    }

    public function telemetry(?Place $place): array
    {
        if ($place === null) {
            return [
                'status' => 'unknown',
                'label' => 'Hours unknown',
                'countdown' => null,
                'badge' => 'unknown',
                'minutes_remaining' => null,
            ];
        }

        if ($this->is24h($place)) {
            return [
                'status' => 'open_24h',
                'label' => 'Open 24 hours',
                'countdown' => '24/7 Service',
                'badge' => 'open_24h',
                'minutes_remaining' => null,
            ];
        }

        $now = now('Asia/Manila');
        $currentDay = (int) $now->dayOfWeek;
        $currentMins = (int) $now->format('G') * 60 + (int) $now->format('i');
        $todayRow = $this->forDay($place, $currentDay);

        if ($todayRow === null || ($todayRow->opens === null && $todayRow->closes === null)) {
            return [
                'status' => 'unknown',
                'label' => 'Hours unknown',
                'countdown' => null,
                'badge' => 'unknown',
                'minutes_remaining' => null,
            ];
        }

        if ($todayRow->is_closed) {
            $tomorrowDay = ($currentDay + 1) % 7;
            $tomorrowRow = $this->forDay($place, $tomorrowDay);
            $opensTomorrow = $tomorrowRow && ! $tomorrowRow->is_closed && $tomorrowRow->opens ? $this->to12h($this->hm($tomorrowRow->opens)) : null;
            $countdown = $opensTomorrow ? "Opens at {$opensTomorrow} tomorrow" : 'Closed today';

            return [
                'status' => 'closed',
                'label' => 'Closed today',
                'countdown' => $countdown,
                'badge' => 'closed',
                'minutes_remaining' => null,
            ];
        }

        $opens = $this->hm($todayRow->opens);
        $closes = $this->hm($todayRow->closes);

        if ($opens === null || $closes === null) {
            return [
                'status' => 'unknown',
                'label' => 'Hours unknown',
                'countdown' => null,
                'badge' => 'unknown',
                'minutes_remaining' => null,
            ];
        }

        $openMins = $this->minutes($opens);
        $closeMins = $this->minutes($closes);

        if ($openMins === $closeMins) {
            return [
                'status' => 'open_24h',
                'label' => 'Open 24 hours',
                'countdown' => '24/7 Service',
                'badge' => 'open_24h',
                'minutes_remaining' => null,
            ];
        }

        if ($closeMins < $openMins) {
            $closeMins += 1440;
        }

        if ($currentMins < $openMins) {
            $diff = $openMins - $currentMins;
            $openStr = $this->to12h($opens);
            $countdown = $diff <= 60 ? "Opens in {$diff} mins" : "Opens at {$openStr}";

            return [
                'status' => 'closed',
                'label' => 'Closed now',
                'countdown' => $countdown,
                'badge' => 'closed',
                'minutes_remaining' => $diff,
            ];
        }

        if ($currentMins >= $openMins && $currentMins < $closeMins) {
            $remaining = $closeMins - $currentMins;
            $closeStr = $this->to12h($closes);
            $status = $remaining <= 60 ? 'closing_soon' : 'open';
            $countdown = $remaining <= 120 ? "Closes in {$remaining} mins" : "Closes at {$closeStr}";

            return [
                'status' => $status,
                'label' => 'Open now',
                'countdown' => $countdown,
                'badge' => $status,
                'minutes_remaining' => $remaining,
            ];
        }

        $tomorrowDay = ($currentDay + 1) % 7;
        $tomorrowRow = $this->forDay($place, $tomorrowDay);
        $opensTomorrow = $tomorrowRow && ! $tomorrowRow->is_closed && $tomorrowRow->opens ? $this->to12h($this->hm($tomorrowRow->opens)) : null;
        $countdown = $opensTomorrow ? "Opens at {$opensTomorrow} tomorrow" : 'Closed now';

        return [
            'status' => 'closed',
            'label' => 'Closed now',
            'countdown' => $countdown,
            'badge' => 'closed',
            'minutes_remaining' => null,
        ];
    }

    public function to12h(?string $time): ?string
    {
        if ($time === null || $time === '' || strlen($time) < 5) {
            return null;
        }

        $h = (int) substr($time, 0, 2);
        $m = substr($time, 3, 2);
        $suffix = $h >= 12 ? 'PM' : 'AM';
        $hr = $h % 12;
        if ($hr === 0) {
            $hr = 12;
        }

        return "{$hr}:{$m} {$suffix}";
    }

    private function hoursFor(Place $place): Collection
    {
        if ($place->relationLoaded('hours')) {
            return $place->hours;
        }

        return $place->hours()->get();
    }

    private function withinWindow(string $opens, string $closes, string $at): bool
    {
        $open = $this->minutes($opens);
        $close = $this->minutes($closes);
        $current = $this->minutes($at);

        if ($open === $close) {
            return true;
        }

        if ($close < $open) {
            $close += 1440;
        }

        return $current >= $open && $current < $close;
    }

    private function minutes(string $time): int
    {
        return ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
    }

    private function hm(string|\DateTimeInterface|null $time): ?string
    {
        if ($time === null) {
            return null;
        }

        $value = (string) $time;

        if ($value === '') {
            return null;
        }

        return strlen($value) >= 5 ? substr($value, 0, 5) : str_pad($value, 5, '0', STR_PAD_LEFT);
    }
}