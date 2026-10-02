<?php

namespace App\Enums;

enum PlaceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Archived = 'archived';
}