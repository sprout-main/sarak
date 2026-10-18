<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StorageFileController extends Controller
{
    /**
     * Fallback file delivery for shared hosting environments where symlinks
     * are unavailable and storage paths may vary (htdocs/storage/app/public vs htdocs/public/storage).
     */
    public function show(string $path): BinaryFileResponse
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = trim($normalized, '/');

        // Prevent directory traversal attacks
        if (str_contains($normalized, '..') || str_starts_with($normalized, '.')) {
            abort(404);
        }

        $candidates = [
            public_path('storage/' . $normalized),
            storage_path('app/public/' . $normalized),
            storage_path($normalized),
            base_path('public/storage/' . $normalized),
            base_path('storage/app/public/' . $normalized),
            base_path('storage/' . $normalized),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                $mime = mime_content_type($candidate) ?: 'application/octet-stream';
                if (preg_match('/\.(jpe?g)$/i', $candidate)) {
                    $mime = 'image/jpeg';
                } elseif (preg_match('/\.png$/i', $candidate)) {
                    $mime = 'image/png';
                } elseif (preg_match('/\.webp$/i', $candidate)) {
                    $mime = 'image/webp';
                } elseif (preg_match('/\.svg$/i', $candidate)) {
                    $mime = 'image/svg+xml';
                }

                return response()->file($candidate, [
                    'Content-Type' => $mime,
                    'Cache-Control' => 'public, max-age=31536000, immutable',
                    'Access-Control-Allow-Origin' => '*',
                ]);
            }
        }

        abort(404, 'File not found');
    }
}
