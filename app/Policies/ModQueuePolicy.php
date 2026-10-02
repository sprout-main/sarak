<?php

namespace App\Policies;

use App\Models\ModQueue;
use App\Models\User;

class ModQueuePolicy
{
    public function approve(User $user, ModQueue $queue): bool
    {
        return $user->isAdmin();
    }

    public function reject(User $user, ModQueue $queue): bool
    {
        return $user->isAdmin();
    }

    public function merge(User $user, ModQueue $queue): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ModQueue $queue): bool
    {
        return $user->isAdmin();
    }
}