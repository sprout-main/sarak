<?php

namespace App\Policies;

use App\Models\Place;
use App\Models\User;

class PlacePolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Place $place): bool
    {
        return $user->isAdmin();
    }

    public function updateLocation(User $user, Place $place): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Place $place): bool
    {
        return $user->isAdmin();
    }
}