<?php

return [
    /*
    |----------------------------------------------------------------------
    | Customer Site Lifecycle Configuration
    |----------------------------------------------------------------------
    */

    // Days after expiration before site is suspended (grace period)
    'grace_period_days' => (int) env('LIFECYCLE_GRACE_PERIOD_DAYS', 15),

    // Days after suspension before site is archived
    'archive_after_days' => (int) env('LIFECYCLE_ARCHIVE_AFTER_DAYS', 90),

    // Days after archival before site is permanently deleted
    'delete_after_days' => (int) env('LIFECYCLE_DELETE_AFTER_DAYS', 365),

    // Fees (in Rials / smallest currency unit)
    'reactivation_fee'     => (int) env('LIFECYCLE_REACTIVATION_FEE', 0),
    'archive_restore_fee'  => (int) env('LIFECYCLE_ARCHIVE_RESTORE_FEE', 0),
    'storage_retention_fee'=> (int) env('LIFECYCLE_STORAGE_RETENTION_FEE', 0),
];
