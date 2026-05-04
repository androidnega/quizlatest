<?php

return [

    'enabled' => filter_var(env('BREAK_GLASS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'owner_username' => env('BREAK_GLASS_OWNER_USERNAME', 'manuel'),

    'owner_phone' => env('BREAK_GLASS_OWNER_PHONE', ''),

    'secret_hash' => env('BREAK_GLASS_SECRET_HASH', ''),

    'attempts' => (int) env('BREAK_GLASS_ATTEMPTS', 3),

    'decay_minutes' => (int) env('BREAK_GLASS_DECAY_MINUTES', 60),

];
