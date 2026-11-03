<?php

return [
    // A student punch marks a session from this many minutes before its start time until its end time.
    'early_minutes' => (int) env('BIOMETRIC_EARLY_MINUTES', 30),

    // Used when a session has no late_threshold_minutes value.
    'late_minutes' => 15,

    // Punches more than this many minutes ahead of the server clock are logged but not used (wrong machine clock).
    'future_tolerance_minutes' => 10,

    // Hikvision access events (majorEventType 5) that mean access was granted.
    // 1 card pass, 38 fingerprint pass, 75 face pass. Failed face/finger and denied events are ignored.
    // If a terminal uses a combined mode (face + finger), add its pass code here and run php artisan config:clear.
    'hikvision' => [
        'pass_sub_events' => [1, 38, 75],
    ],
];
