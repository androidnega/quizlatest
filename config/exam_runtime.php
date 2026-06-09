<?php

return [

    'session_lock_ttl_seconds' => (int) env('EXAM_SESSION_LOCK_TTL', 60),

    'exam_config_ttl_seconds' => (int) env('EXAM_CONFIG_CACHE_TTL', 480),

    'exam_start_window_seconds' => (int) env('EXAM_START_RATE_WINDOW', 60),

    'exam_start_max_attempts' => (int) env('EXAM_START_RATE_MAX', 30),

    'proctoring_events_window_seconds' => (int) env('EXAM_PROCTORING_FLOOD_WINDOW', 60),

    'proctoring_events_max_per_window' => (int) env('EXAM_PROCTORING_FLOOD_MAX', 200),

];
