<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit log retention (days)
    |--------------------------------------------------------------------------
    |
    | Rows in activity_logs older than this many days are removed by
    | `php artisan activity-log:prune`. Override with AUDIT_RETENTION_DAYS.
    |
    */
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),
    'prune_dry_run' => filter_var(env('AUDIT_PRUNE_DRY_RUN', false), FILTER_VALIDATE_BOOLEAN),
];
