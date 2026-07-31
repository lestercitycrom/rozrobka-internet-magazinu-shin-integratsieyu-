<?php

return ['enabled' => (bool) env('VEHICLE_FITMENT_ENABLED', false), 'endpoint' => env('VEHICLE_FITMENT_ENDPOINT', 'https://www.vegvesen.no/ws/no/vegvesen/kjoretoy/felles/datautlevering/enkeltoppslag/kjoretoydata'), 'api_key' => env('VEHICLE_FITMENT_API_KEY'), 'header' => env('VEHICLE_FITMENT_AUTH_HEADER', 'SVV-Authorization'), 'timeout' => (int) env('VEHICLE_FITMENT_TIMEOUT', 5), 'cache_ttl' => (int) env('VEHICLE_FITMENT_CACHE_TTL', 300)];
