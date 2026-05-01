<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pending OTP (stored in Redis only)
    |--------------------------------------------------------------------------
    */
    'ttl_seconds' => (int) env('EXAM_OTP_TTL_SECONDS', 300),

    'max_verify_attempts' => (int) env('EXAM_OTP_MAX_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Window after successful OTP verify — student must complete exam start (face)
    |--------------------------------------------------------------------------
    */
    'verified_ttl_seconds' => (int) env('EXAM_OTP_VERIFIED_TTL_SECONDS', 900),

    /*
    |--------------------------------------------------------------------------
    | SMS issue rate limit (per student, rolling window)
    |--------------------------------------------------------------------------
    */
    'max_send_per_window' => (int) env('EXAM_OTP_MAX_SEND_PER_WINDOW', 3),

    'send_window_seconds' => (int) env('EXAM_OTP_SEND_WINDOW_SECONDS', 600),

];
