<?php

return [
    'driver' => env('MATERIAL_IN_DRIVER', 'http'),
    'base_url' => env('MATERIAL_IN_BASE_URL', 'http://103.126.117.169/api/qc/material-ins'),
    'timeout' => (int) env('MATERIAL_IN_TIMEOUT', 5),
];
