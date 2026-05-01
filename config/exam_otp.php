<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pending OTP (stored in Redis only; otp_hash is bcrypt, never plaintext)
    |--------------------------------------------------------------------------
    */
    'ttl_seconds' => (int) env('EXAM_OTP_TTL_SECONDS', 300),

    'max_verify_attempts' => (int) env('EXAM_OTP_MAX_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | After successful verify — window to complete face + session start
    |--------------------------------------------------------------------------
    */
    'verified_ttl_seconds' => (int) env('EXAM_OTP_VERIFIED_TTL_SECONDS', 900),

    /*
    |--------------------------------------------------------------------------
    | Minimum seconds between SMS sends for the same exam OTP flow
    |--------------------------------------------------------------------------
    */
    'sms_resend_cooldown_seconds' => (int) env('EXAM_OTP_SMS_COOLDOWN_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Max OTP **generation / SMS send** operations per student (rolling window)
    |--------------------------------------------------------------------------
    */
    'max_send_per_window' => (int) env('EXAM_OTP_MAX_SEND_PER_WINDOW', 5),

    'send_window_seconds' => (int) env('EXAM_OTP_SEND_WINDOW_SECONDS', 600),

];
