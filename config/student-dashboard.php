<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rotating tips (student dashboard)
    |--------------------------------------------------------------------------
    |
    | Disabled by default so the home screen stays focused on real work items.
    |
    */
    'show_rotating_tips' => (bool) env('DASHBOARD_SHOW_ROTATING_TIPS', false),

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
