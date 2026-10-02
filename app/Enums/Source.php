<?php

namespace App\Enums;

enum Source: string
{
    case Manual = 'manual';
    case Overpass = 'overpass';
    case Nominatim = 'nominatim';
    case OfficialList = 'official_list';
    case GoogleRef = 'google_ref';
    case Community = 'community';
}