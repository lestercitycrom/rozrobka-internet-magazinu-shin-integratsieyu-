<?php

return [
    'disk_free_warning_bytes' => (int) env('EASYTIRE_HEALTH_DISK_FREE_WARNING_BYTES', 5 * 1024 * 1024 * 1024),
    'ndi_success_max_age_minutes' => (int) env('EASYTIRE_HEALTH_NDI_SUCCESS_MAX_AGE_MINUTES', 24 * 60),
];
