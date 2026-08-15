<?php

return [
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
];
