<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class GeoSpatial
{
    private const MISMATCH_SUBQUERY = <<<'SQL'
LEFT JOIN (
    SELECT MIN(x.bar_id) AS resolved_id, x.place_id
    FROM (
        SELECT b.id AS bar_id, pp.id AS place_id
        FROM places pp
        JOIN boundary_areas b
          ON b.type = 'barangay' AND b.geom IS NOT NULL
         AND ST_Contains(b.geom, pp.geom)
        WHERE pp.deleted_at IS NULL
    ) x
    GROUP BY x.place_id
) r ON r.place_id = p.id
SQL;

    private const MISMATCH_PREDICATE = <<<'SQL'
p.deleted_at IS NULL
  AND (
      (r.resolved_id IS NOT NULL AND (p.barangay_id IS NULL OR p.barangay_id <> r.resolved_id))
      OR (r.resolved_id IS NULL AND p.barangay_id IS NOT NULL)
  )
SQL;

    public function haversineM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371008.8;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earthRadius * asin(sqrt($a));
    }

    public function barangayMismatchRows(int $limit = 500): array
    {
        $limit = max(1, $limit);

        $sql = $this->substitute(
            <<<SQL
SELECT p.id AS place_id,
       p.name AS place_name,
       p.barangay_id AS assigned_id,
       b.name AS assigned_name,
       r.resolved_id,
       rb.name AS resolved_name
FROM places p
LEFT JOIN boundary_areas b ON b.id = p.barangay_id
{{MISMATCH_SUBQUERY}}
LEFT JOIN boundary_areas rb ON rb.id = r.resolved_id
WHERE {{MISMATCH_PREDICATE}}
ORDER BY p.id
LIMIT {$limit}
SQL
        );

        return array_map(fn ($row) => (array) $row, DB::select($sql));
    }

    public function fixBarangayMismatch(int $limit = 500): int
    {
        $limit = max(1, $limit);

        $sql = $this->substitute(
            <<<SQL
UPDATE places p
LEFT JOIN boundary_areas b ON b.id = p.barangay_id
{{MISMATCH_SUBQUERY}}
SET p.barangay_id = r.resolved_id
WHERE {{MISMATCH_PREDICATE}}
ORDER BY p.id
LIMIT {$limit}
SQL
        );

        return DB::update($sql);
    }

    private function substitute(string $sql): string
    {
        return str_replace(
            ['{{MISMATCH_SUBQUERY}}', '{{MISMATCH_PREDICATE}}'],
            [self::MISMATCH_SUBQUERY, self::MISMATCH_PREDICATE],
            $sql,
        );
    }
}