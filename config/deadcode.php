<?php

return [
    'supervisor_binary' => env('DEADCODE_SUPERVISOR_BINARY', base_path('../go-supervisor/bin/deadcode-supervisor')),
    'supervisor_timeout' => (int) env('DEADCODE_SUPERVISOR_TIMEOUT', 300),
];
