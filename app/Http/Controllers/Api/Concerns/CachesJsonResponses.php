<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait CachesJsonResponses
{
    private function cachedJson(Request $request, mixed $data, int $maxAge = 30, array $headers = []): JsonResponse
    {
        $response = response()->json($data, 200, $headers);

        $etag = '"' . md5($response->getContent()) . '"';

        if (property_exists($this, 'atlas') && $this->atlas instanceof \App\Services\AtlasCache) {
            $etagKey = md5($request->getQueryString() ?? '');
            $this->atlas->setEtag($etagKey, $etag, $maxAge);
        }

        $response->header('Cache-Control', 'public, max-age=' . $maxAge);
        $response->header('ETag', $etag);
        $response->header('Vary', 'Accept');

        $ifNoneMatch = $request->header('If-None-Match');

        if (is_string($ifNoneMatch) && $ifNoneMatch !== '' && $this->etagMatches($ifNoneMatch, $etag)) {
            return $response->setStatusCode(304)->setContent('');
        }

        return $response;
    }

    private function etagMatches(string $header, string $etag): bool
    {
        foreach (explode(',', $header) as $candidate) {
            if (trim($candidate) === $etag) {
                return true;
            }
        }

        return false;
    }
}