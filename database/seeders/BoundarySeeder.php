<?php

namespace Database\Seeders;

use App\Models\BoundaryArea;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BoundarySeeder extends Seeder
{
    public const SOURCE_STRING = 'PSGC 2023 via faeldon/philippines-json-maps (medres)';

    private const CANDIDATE_URLS = [
        'https://raw.githubusercontent.com/faeldon/philippines-json-maps/master/2023/geojson/municities/medres/bgysubmuns-municity-1400101000.0.01.json',
        'https://raw.githubusercontent.com/faeldon/philippines-json-maps/main/2023/geojson/municities/medres/bgysubmuns-municity-1400101000.0.01.json',
        'https://cdn.jsdelivr.net/gh/faeldon/philippines-json-maps@master/2023/geojson/municities/medres/bgysubmuns-municity-1400101000.0.01.json',
        'https://api.github.com/repos/faeldon/philippines-json-maps/contents/2023/geojson/municities/medres/bgysubmuns-municity-1400101000.0.01.json',
    ];

    public function run(): void
    {
        if (BoundaryArea::count() > 0) {
            return;
        }

        $geojson = $this->fetchGeoJson();

        if ($geojson === null) {
            $this->writeErrorLog();

            return;
        }

        $this->importBoundaries($geojson);
    }

    private function fetchGeoJson()
    {
        foreach (self::CANDIDATE_URLS as $url) {
            try {
                $response = Http::timeout(30)->withoutVerifying()->get($url);
            } catch (\Throwable $e) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $payload = $response->json();

            if (! is_array($payload) || ! isset($payload['features']) || ! is_array($payload['features'])) {
                continue;
            }

            return $payload;
        }

        return null;
    }

    private function importBoundaries(array $geojson): void
    {
        $now = now();

        foreach ($geojson['features'] as $feature) {
            if (! isset($feature['geometry'], $feature['geometry']['type'])) {
                continue;
            }

            $properties = $feature['properties'] ?? [];
            $name = $properties['adm4_en'] ?? null;
            $psgc = (string) ($properties['adm4_psgc'] ?? '');

            if ($name === null) {
                continue;
            }

            $geometryJson = json_encode($feature['geometry']);

            $geomSql = "ST_GeomFromWKB(ST_AsBinary(ST_GeomFromGeoJSON('" . addslashes($geometryJson) . "')), 4326)";

            BoundaryArea::create([
                'name' => $name,
                'type' => 'barangay',
                'geom' => DB::raw($geomSql),
                'boundary_source' => self::SOURCE_STRING,
                'psgc' => $psgc,
                'imported_at' => $now,
            ]);
        }
    }

    private function writeErrorLog(): void
    {
        $logPath = database_path('seeders/logs/boundary-seed-error.txt');

        $lines = ['BoundarySeeder could not fetch Bangued barangay GeoJSON.', 'URLs tried:'];

        foreach (self::CANDIDATE_URLS as $url) {
            $lines[] = '  ' . $url;
        }

        $lines[] = 'Timestamp: ' . now()->toDateTimeString();

        if (! is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }

        file_put_contents($logPath, implode(PHP_EOL, $lines) . PHP_EOL);
    }
}