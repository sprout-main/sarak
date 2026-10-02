<?php

namespace App\Providers;

use App\Models\BoundaryArea;
use App\Models\Place;
use App\Services\AtlasCache;
use Illuminate\Support\ServiceProvider;

class AtlasCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AtlasCache::class);
    }

    public function boot(): void
    {
        foreach (['created', 'updated', 'deleted', 'restored'] as $event) {
            Place::{$event}(function () {
                app(AtlasCache::class)->invalidate();
            });
        }
        foreach (['created', 'updated', 'deleted'] as $event) {
            BoundaryArea::{$event}(function () {
                app(AtlasCache::class)->invalidate();
            });
        }
    }
}