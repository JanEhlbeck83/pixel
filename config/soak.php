<?php

return [
    'enabled' => (bool) env('SOAK_TEST_ENABLED', false),
    'manifest' => storage_path('app/private/soak/manifest.json'),
];
