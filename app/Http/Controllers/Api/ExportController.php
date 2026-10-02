<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeoJsonExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private GeoJsonExporter $exporter)
    {
    }

    public function geojson(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $crs = $this->exporter->crs();
        $date = date('Y-m-d');

        return response()->stream(function () use ($filters, $crs) {
            $out = fopen('php://output', 'wb');
            fwrite($out, '{"type":"FeatureCollection","crs":' . json_encode($crs) . ',"features":[');

            $first = true;
            $this->exporter->writeFeatures($filters, function (array $feature) use ($out, &$first) {
                fwrite($out, ($first ? '' : ',') . json_encode($feature, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                $first = false;
            });

            fwrite($out, ']}');
            fclose($out);
        }, 200, [
            'Content-Type' => 'application/geo+json',
            'Content-Disposition' => 'inline; filename="sarak-' . $date . '.geojson"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $date = date('Y-m-d');
        $header = [
            'name', 'slug', 'category', 'category_color', 'address', 'barangay', 'phone', 'website',
            'verified_tier', 'source', 'lat', 'lng',
            'open_now', 'hours_today_opens', 'hours_today_closes', 'hours_today_is_closed', 'hours_today_note',
        ];

        return response()->stream(function () use ($filters, $header) {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $header);

            $this->exporter->writeFeatures($filters, function (array $feature) use ($output) {
                $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
                $today = is_array($properties['hours_today'] ?? null) ? $properties['hours_today'] : [];

                fputcsv($output, [
                    $properties['name'] ?? null,
                    $properties['slug'] ?? null,
                    $properties['category'] ?? null,
                    $properties['category_color'] ?? null,
                    $properties['address'] ?? null,
                    $properties['barangay'] ?? null,
                    $properties['phone'] ?? null,
                    $properties['website'] ?? null,
                    $properties['verified_tier'] ?? null,
                    $properties['source'] ?? null,
                    $properties['lat'] ?? null,
                    $properties['lng'] ?? null,
                    $this->booleanText($properties['open_now'] ?? null),
                    $today['opens'] ?? null,
                    $today['closes'] ?? null,
                    $this->booleanText($today['is_closed'] ?? null),
                    $today['note'] ?? null,
                ]);
            });

            fclose($output);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sarak-' . $date . '.csv"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function filters(Request $request): array
    {
        $categories = $request->query('categories');
        $categorySlugs = is_string($categories) && trim($categories) !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $categories))))
            : [];

        $bbox = $request->query('bbox');

        return [
            'categories' => $categorySlugs,
            'bbox' => is_string($bbox) && trim($bbox) !== '' ? $bbox : null,
        ];
    }

    private function booleanText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return $value ? 'yes' : 'no';
    }
}