<?php

return [
    'mysqldump_binary' => env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),
    'retention' => (int) env('BACKUP_RETENTION', 14),
];
