<?php

return [
    'driver' => env('VSM_DRIVER', 'http'),
    'base_url' => env('VSM_BASE_URL', 'http://103.126.117.169:8083/api/vsm'),
    'token' => env('VSM_TOKEN'),
    'timeout' => (int) env('VSM_TIMEOUT', 6),
];
