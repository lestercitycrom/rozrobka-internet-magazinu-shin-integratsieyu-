<?php

return [
    // No production provider is assumed. An adapter must be reviewed and bound
    // explicitly before this flag may be enabled.
    'enabled' => (bool) env('PAYMENTS_ENABLED', false),
    'provider' => env('PAYMENTS_PROVIDER'),
];
