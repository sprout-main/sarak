<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TileProxyController extends Controller
{
    protected array $styles = [
        'dark' => 'dark_all',
        'light' => 'light_all',
    ];

    public function show(Request $request, string $style, int $z, int $x, int $y)
    {
        if (!isset($this->styles[$style])) {
            abort(404);
        }

        $limit = 1 << $z;
        if ($z < 0 || $z > 20 || $x < 0 || $y < 0 || $x >= $limit || $y >= $limit) {
            abort(404);
        }

        $retina = (bool) $request->route('retina');
        $cacheDir = storage_path("app/tiles/{$style}/{$z}/{$x}");
        $cacheFile = $cacheDir . '/' . $y . ($retina ? '@2x' : '') . '.png';

        if (file_exists($cacheFile) && filesize($cacheFile) > 0) {
            return response()->file($cacheFile, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=604800, immutable',
            ]);
        }

        $key = config('services.carto.basemap_key');
        $cartoStyle = $this->styles[$style];

        $url = "https://a.basemaps.cartocdn.com/{$cartoStyle}/{$z}/{$x}/{$y}" . ($retina ? '@2x' : '') . '.png';

        $response = Http::withHeaders([
            'User-Agent' => 'Sarak/1.0',
            'Referer' => config('app.url'),
        ])->timeout(10)
            ->get($url, array_filter(['key' => $key]));

        if (!$response->successful()) {
            Log::warning('Tile proxy upstream failed', [
                'url' => $url,
                'status' => $response->status(),
            ]);
            abort(502);
        }

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        @file_put_contents($cacheFile, $response->body());

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type') ?: 'image/png',
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }
}