<?php

namespace App\Enums;

enum VerificationTier: string
{
    case Verified = 'verified';
    case Community = 'community';
    case Imported = 'imported';
}