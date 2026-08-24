<?php

return [
    // Keep disabled on staging. Enable only after production SMTP and sender-domain verification.
    'delivery_enabled' => (bool) env('CUSTOMER_EMAIL_DELIVERY_ENABLED', false),
];
