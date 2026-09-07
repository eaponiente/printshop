<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Late Deduction Per Minute
    |--------------------------------------------------------------------------
    |
    | Flat peso amount charged for each minute of lateness within the
    | threshold window (see late_deduction_threshold_minutes below).
    | Current value is read from the payroll_settings DB table and
    | falls back to this env default when no DB override exists.
    |
    */

    'late_deduction_per_minute' => env('PAYROLL_LATE_DEDUCTION_PER_MINUTE', 10),

    /*
    |--------------------------------------------------------------------------
    | Late Deduction Threshold Minutes
    |--------------------------------------------------------------------------
    |
    | Number of minutes after which the late penalty switches from the
    | flat per-minute rate to the hourly-rate-based formula. The value
    | is read from payroll_settings first and falls back to this env.
    |
    */

    'late_deduction_threshold_minutes' => env('PAYROLL_LATE_DEDUCTION_THRESHOLD_MINUTES', 20),

    /*
    |--------------------------------------------------------------------------
    | Half-Day Threshold Minutes
    |--------------------------------------------------------------------------
    |
    | Arriving this many minutes (or more) after the schedule start turns the
    | day into an afternoon-only half day: the morning is unpaid, no late
    | deduction is applied, and only the afternoon session is paid. Read from
    | payroll_settings first, falling back to this env value.
    |
    */

    'half_day_threshold_minutes' => env('PAYROLL_HALF_DAY_THRESHOLD_MINUTES', 60),

    'no_break_fine' => env('PAYROLL_NO_BREAK_FINE', 20),

    /*
    |--------------------------------------------------------------------------
    | Max Overtime Minutes
    |--------------------------------------------------------------------------
    |
    | Sanity cap on the OVERTIME_IN -> OVERTIME_OUT punch diff (after the
    | midnight-rollover adjustment). A span longer than this is treated as an
    | implausible/mis-stamped punch: the raw minutes are still recorded on the
    | sheet for review, but no overtime pay is computed and the sheet is
    | flagged incomplete. Read from payroll_settings first, falling back to
    | this env value.
    |
    */

    'max_overtime_minutes' => env('PAYROLL_MAX_OVERTIME_MINUTES', 720),

];
