<?php

$designProfile = env('STOREFRONT_DESIGN_PROFILE', 'modern');
$designProfile = is_string($designProfile) && in_array($designProfile, ['modern', 'classic'], true)
    ? $designProfile
    : 'modern';

$canonicalOrigin = rtrim((string) env('STOREFRONT_CANONICAL_ORIGIN', 'https://staging-easytire.bethub.fun'), '/');
if (filter_var($canonicalOrigin, FILTER_VALIDATE_URL) === false || parse_url($canonicalOrigin, PHP_URL_SCHEME) !== 'https') {
    $canonicalOrigin = 'https://staging-easytire.bethub.fun';
}

return [
    'design_profile' => $designProfile,
    'canonical_origin' => $canonicalOrigin,
    'ru_preview_enabled' => (bool) env('STOREFRONT_RU_PREVIEW_ENABLED', false),
    'stock_max_age_minutes' => (int) env('STOREFRONT_STOCK_MAX_AGE_MINUTES', 180),
    'price_max_age_minutes' => (int) env('STOREFRONT_PRICE_MAX_AGE_MINUTES', 1560),
    'phone_display' => '+47 939 58 270',
    'phone_href' => '+4793958270',
    'email' => 'max.easytire@gmail.com',
    'legal_name' => 'EasyTask AS',
    'organisation_number' => '934 860 187',
    'business_address' => 'Ringvegen 200, 9018 Tromsø',
    'map_pin_address' => 'Ringvegen 202, 9018 Tromsø',
    'map_url' => 'https://maps.app.goo.gl/cWq1CsBFJXH9mykF8?g_st=ic',
    'map_embed_url' => env('STOREFRONT_MAP_EMBED_URL')
        ?: 'https://www.google.com/maps?q=EasyTire%20Dekkskift%20Troms%C3%B8%2C%20Ringvegen%20202%2C%209018%20Troms%C3%B8&output=embed',
    'facebook_url' => 'https://www.facebook.com/share/1Bgf5oF3bL/?mibextid=wwXIfr',
    'instagram_url' => 'https://www.instagram.com/easytire_tromso/',
    // Stable finder vocabulary, independent of supplier freshness. These values
    // narrow the existing catalogue filters and never claim stock or fitment.
    'tyre_dimensions' => [
        'widths' => [125, 135, 145, 155, 165, 175, 185, 195, 205, 215, 225, 235, 245, 255, 265, 275, 285, 295, 305, 315, 325, 335, 345, 355],
        'aspects' => [25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80],
        'rims' => [12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24],
    ],
];
