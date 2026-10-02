<?php

namespace App\Providers;

use App\Models\ModQueue;
use App\Models\Place;
use App\Models\PlacePhoto;
use App\Policies\ModQueuePolicy;
use App\Policies\PlacePhotoPolicy;
use App\Policies\PlacePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Place::class, PlacePolicy::class);
        Gate::policy(ModQueue::class, ModQueuePolicy::class);
        Gate::policy(PlacePhoto::class, PlacePhotoPolicy::class);
    }
}
