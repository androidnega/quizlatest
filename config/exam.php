<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Essay marking guide on publish
    |--------------------------------------------------------------------------
    |
    | When true, every approved essay question must include marking guide,
    | rubric, or sample answer metadata before the assessment can be published.
    | Per-quiz override: proctoring_settings.require_essay_marking_guide_on_publish
    |
    */
    'require_essay_marking_guide_on_publish' => (bool) env('EXAM_REQUIRE_ESSAY_MARKING_GUIDE_PUBLISH', false),

    /*
    |--------------------------------------------------------------------------
    | Timed exam disconnect pause
    |--------------------------------------------------------------------------
    |
    | If a student's session is still "active" but we have not received any
    | activity (state poll, answer save, or heartbeat) for this many seconds,
    | the session is moved to "paused" so the countdown stops until they resume.
    |
    */
    'disconnect_pause_threshold_seconds' => (int) env('EXAM_DISCONNECT_PAUSE_THRESHOLD', 120),

];
