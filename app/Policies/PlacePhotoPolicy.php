<?php

namespace App\Policies;

use App\Enums\PhotoStatus;
use App\Models\PlacePhoto;
use App\Models\User;

class PlacePhotoPolicy
{
    public function delete(User $user, PlacePhoto $photo): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $photo->uploader_id === $user->id
            && in_array($photo->status, [PhotoStatus::Pending->value, PhotoStatus::Rejected->value], true);
    }

    /**
     * A contributor can only flag their own pending upload as the cover; the
     * flag is honoured when an administrator approves it.
     */
    public function updateCover(User $user, PlacePhoto $photo): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $photo->uploader_id === $user->id
            && $photo->status === PhotoStatus::Pending->value;
    }
}
