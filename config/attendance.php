<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Staff Work Hours and Attendance Policies
    |--------------------------------------------------------------------------
    |
    | Configurable shift hours, late grace period threshold, and standard daily
    | working hours for Skincare Boutique employees.
    |
    */

    'work_start_time' => env('ATTENDANCE_WORK_START_TIME', '09:00:00'),
    'late_threshold_time' => env('ATTENDANCE_LATE_THRESHOLD_TIME', '09:15:00'),
    'work_end_time' => env('ATTENDANCE_WORK_END_TIME', '18:00:00'),
    'standard_daily_hours' => (float) env('ATTENDANCE_STANDARD_DAILY_HOURS', 8.0),

    /*
    |--------------------------------------------------------------------------
    | Allowed Attendance Statuses
    |--------------------------------------------------------------------------
    */
    'statuses' => [
        'present' => 'Present',
        'late' => 'Late',
        'permission' => 'Permission',
        'absent' => 'Absent',
    ],
];
