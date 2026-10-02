<?php

namespace App\Services;

use App\Models\BoundaryArea;
use Illuminate\Support\Facades\DB;

class BarangayResolver
{
    public function resolve(float $lat, float $lng): ?BoundaryArea
    {
        $id = $this->resolveId($lat, $lng);

        return $id !== null ? BoundaryArea::query()->find($id) : null;
    }

    public function resolveId(float $lat, float $lng): ?int
    {
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return null;
        }

        $point = "ST_GeomFromText('POINT({$lng} {$lat})', 4326)";

        $id = DB::table('boundary_areas')
            ->where('type', 'barangay')
            ->whereNotNull('geom')
            ->whereRaw("MBRContains(geom, {$point})")
            ->whereRaw("ST_Contains(geom, {$point})")
            ->orderBy('id')
            ->limit(1)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}