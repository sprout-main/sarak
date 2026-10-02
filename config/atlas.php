<?php

return [
    'enrich_addresses' => env('ATLAS_ENRICH_ADDRESSES', true),
    'nominatim_min_interval' => env('ATLAS_NOMINATIM_MIN_INTERVAL', 1),
    'import_chunk_size' => env('ATLAS_IMPORT_CHUNK_SIZE', 250),
    'overpass_max_attempts' => env('ATLAS_OVERPASS_MAX_ATTEMPTS', 4),
    'overpass_base_delay' => env('ATLAS_OVERPASS_BASE_DELAY', 1),
    'overpass_endpoints' => [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
        'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
    ],
    'dedupe_fuzzy_meters' => env('ATLAS_DEDUPE_FUZZY_METERS', 150),
    'dedupe_phone_meters' => env('ATLAS_DEDUPE_PHONE_METERS', 2000),
];