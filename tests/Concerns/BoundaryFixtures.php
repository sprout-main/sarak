<?php

namespace Tests\Concerns;

use App\Models\BoundaryArea;
use Illuminate\Support\Facades\DB;

trait BoundaryFixtures
{
    protected function polygon(string $name, array $ring, array $extra = []): BoundaryArea
    {
        $closed = array_merge($ring, [$ring[0]]);
        $points = implode(',', array_map(
            fn (array $coord) => $coord[1] . ' ' . $coord[0],
            $closed
        ));

        return BoundaryArea::create(array_merge([
            'name' => $name,
            'type' => 'barangay',
            'geom' => DB::raw("ST_GeomFromText('POLYGON(({$points}))', 4326)"),
        ], $extra));
    }

    protected function boxRing(float $south, float $west, float $north, float $east): array
    {
        return [
            [$south, $west],
            [$south, $east],
            [$north, $east],
            [$north, $west],
        ];
    }
}