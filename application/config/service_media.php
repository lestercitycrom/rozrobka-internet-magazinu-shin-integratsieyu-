<?php

return [
    'provenance' => 'Approved EasyTire repository media supplied for the public storefront.',

    'assets' => [
        'mobile-service' => ['file' => 'mobile-service.webp', 'source' => 'customer-local-mobile-service-photo'],
        'workshop' => ['file' => 'workshop.webp', 'source' => 'customer-local-workshop-photo'],
        'workshop-alt' => ['file' => 'workshop-alt.webp', 'source' => 'customer-local-workshop-photo-alt'],
        'dekkhotell' => ['file' => 'dekkhotell.webp', 'source' => 'customer-local-tire-storage-photo'],
        'exterior' => ['file' => 'exterior.webp', 'source' => 'customer-local-exterior-fleet-photo'],
    ],

    /*
     * Every seeded/published service has one deterministic presentation.
     * Reuse is deliberately limited to semantically defensible workshop/fleet contexts.
     * `fallback` means that no approved service-specific photograph is claimed.
     */
    'services' => [
        'mobilt-dekkskift' => ['asset' => 'mobile-service', 'crop' => 'mobile-service', 'feature' => true],
        'dekkverksted' => ['asset' => 'workshop-alt', 'crop' => 'workshop-wide'],
        'tesla-og-elbil' => ['asset' => 'mobile-service', 'crop' => 'vehicle'],
        'bobil-og-varebil' => ['asset' => 'exterior', 'crop' => 'fleet'],
        'dekkhotell' => ['asset' => 'dekkhotell', 'crop' => 'storage', 'feature' => true],
        'dekk-felg-og-hjulpakker' => ['fallback' => 'wheel-fitment'],
        'omlegging-balansering' => ['asset' => 'workshop', 'crop' => 'equipment'],
        'dekkreparasjon' => ['asset' => 'workshop-alt', 'crop' => 'equipment-right'],
        'tpms-sensor' => ['fallback' => 'wheel-electronics'],
        'oljeskift' => ['fallback' => 'vehicle-care'],
        'bilbatteri' => ['asset' => 'exterior', 'crop' => 'vehicle'],
        'bildiagnose' => ['asset' => 'workshop', 'crop' => 'workshop-wide'],
        'lyspaerer-viskerblad' => ['fallback' => 'vehicle-care'],
    ],

    'fallback' => ['fallback' => 'local-service'],
];
