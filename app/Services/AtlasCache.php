<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AtlasCache
{
    private const TTL_SECONDS = 300;

    private const TOTAL_APPROVED = 'atlas:total_approved:v1';

    private const CATEGORIES = 'atlas:categories:v1';

    private const BARANGAYS = 'atlas:barangays:v1';

    private const BOUNDARIES = 'atlas:boundaries:v1';

    private const EPOCH = 'atlas:epoch:v1';

    public function epoch(): int
    {
        return (int) Cache::get(self::EPOCH, 1);
    }

    public function getEtag(string $key): ?string
    {
        return Cache::get('atlas:etag:' . $this->epoch() . ':' . $key);
    }

    public function setEtag(string $key, string $etag, int $ttl = 300): void
    {
        Cache::put('atlas:etag:' . $this->epoch() . ':' . $key, $etag, $ttl);
    }

    public function totalApproved(callable $fresh): int
    {
        return (int) Cache::remember(self::TOTAL_APPROVED, self::TTL_SECONDS, $fresh);
    }

    public function categories(callable $fresh): array
    {
        return Cache::remember(self::CATEGORIES, self::TTL_SECONDS, $fresh);
    }

    public function barangays(callable $fresh): array
    {
        return Cache::remember(self::BARANGAYS, self::TTL_SECONDS, $fresh);
    }

    public function boundaries(callable $fresh): array
    {
        return Cache::remember(self::BOUNDARIES, self::TTL_SECONDS, $fresh);
    }

    public function invalidate(): void
    {
        Cache::forget(self::TOTAL_APPROVED);
        Cache::forget(self::CATEGORIES);
        Cache::forget(self::BARANGAYS);
        Cache::forget(self::BOUNDARIES);
        try {
            Cache::increment(self::EPOCH);
        } catch (\Throwable) {
            Cache::put(self::EPOCH, time(), self::TTL_SECONDS * 10);
        }
    }
}