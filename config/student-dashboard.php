<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Optional institution policy banner (student dashboard)
    |--------------------------------------------------------------------------
    |
    | When `version` is greater than the student's `policy_notice_ack_version`,
    | a dismissible notice is shown. Bump `version` when messaging changes.
    |
    */
    'policy' => [
        'version' => (int) env('DASHBOARD_POLICY_VERSION', 0),
        'message' => env('DASHBOARD_POLICY_MESSAGE', ''),
        'faq_url' => env('DASHBOARD_POLICY_FAQ_URL', ''),
    ],
];
