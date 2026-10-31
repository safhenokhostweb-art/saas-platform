<?php

return [
    // Default plan to apply when no per-site override exists
    'default_plan' => env('RESOURCE_DEFAULT_PLAN', 'basic'),

    // Storage limit per site (bytes) — default 1 GB
    'storage_limit_bytes' => (int) env('RESOURCE_STORAGE_LIMIT_BYTES', 1073741824),

    // Monthly bandwidth limit per site (bytes) — default 10 GB
    'bandwidth_limit_bytes' => (int) env('RESOURCE_BANDWIDTH_LIMIT_BYTES', 10737418240),

    // Archive retention (days) — how long archives are kept before deletion warning
    'archive_retention_days' => (int) env('RESOURCE_ARCHIVE_RETENTION_DAYS', 365),

    // Backup retention (days)
    'backup_retention_days' => (int) env('RESOURCE_BACKUP_RETENTION_DAYS', 30),

    // Capacity weights (1–100, relative share of VPS resources)
    'cpu_weight' => (int) env('RESOURCE_CPU_WEIGHT', 10),
    'ram_weight' => (int) env('RESOURCE_RAM_WEIGHT', 10),

    // Overage fees (Rials per GB, 0 = not billed)
    'storage_overage_fee_per_gb'   => (int) env('RESOURCE_STORAGE_OVERAGE_FEE', 0),
    'bandwidth_overage_fee_per_gb' => (int) env('RESOURCE_BANDWIDTH_OVERAGE_FEE', 0),
    'archive_retention_fee'        => (int) env('RESOURCE_ARCHIVE_RETENTION_FEE', 0),
    'restore_fee'                  => (int) env('RESOURCE_RESTORE_FEE', 0),

    // Alert thresholds (percent of limit)
    'alert_warning_percent'  => (int) env('RESOURCE_ALERT_WARNING_PCT', 80),
    // Also drive the DevOps disk danger zones: below warning = normal,
    // warning..critical = warning, at or above critical = critical.
    'alert_critical_percent' => (int) env('RESOURCE_ALERT_CRITICAL_PCT', 90),

    // Snapshot retention (days) — old daily snapshots purged after this
    'snapshot_retention_days' => (int) env('RESOURCE_SNAPSHOT_RETENTION_DAYS', 90),

    // Archive growth anomaly threshold (bytes/day to trigger alert)
    'archive_growth_anomaly_bytes' => (int) env('RESOURCE_ARCHIVE_GROWTH_ANOMALY', 104857600), // 100 MB/day

    /*
    |--------------------------------------------------------------------------
    | DevOps disk governance (measured web-root usage, not the DB estimate)
    |--------------------------------------------------------------------------
    | Thin-provisioned: the sum of soft limits may exceed the physical disk of
    | the tenant's server. Per-site overrides live in site_resource_limits.
    */
    'disk' => [
        'soft_limit_bytes'  => (int) env('RESOURCE_DISK_SOFT_LIMIT_BYTES', 5368709120), // 5 GB
        'hard_limit_bytes'  => (int) env('RESOURCE_DISK_HARD_LIMIT_BYTES', 0),          // 0 = none
        // Seconds a server-metrics reading stays cached on the panel host.
        'metrics_cache_seconds' => (int) env('RESOURCE_METRICS_CACHE_SECONDS', 30),
        // Seconds a remote du/find call may run before it is treated as failed.
        'remote_timeout_seconds' => (int) env('RESOURCE_REMOTE_TIMEOUT_SECONDS', 120),
    ],

    'prune' => [
        // When false, the scheduler only measures and records dry-run previews;
        // nothing is deleted until an admin runs a prune or this is enabled.
        'auto_enabled' => (bool) env('RESOURCE_AUTO_PRUNE_ENABLED', false),

        // Minutes a dry-run confirmation token stays valid for the UI prune.
        'confirmation_ttl_minutes' => 10,

        // Safety caps per run.
        'max_files_per_run' => (int) env('RESOURCE_PRUNE_MAX_FILES', 20000),
        'delete_batch_size' => 200,

        // Retention per category, in days (sessions in minutes). The
        // "aggressive" profile is used for critical sites by the scheduler.
        'profiles' => [
            'routine' => [
                'logs_days'           => 14,
                'cache_days'          => 7,
                'views_days'          => 7,
                'sessions_minutes'    => 1440,
                'temp_days'           => 2,
                'dumps_days'          => 30,
                'dumps_keep_newest'   => 3,
            ],
            'aggressive' => [
                'logs_days'           => 3,
                'cache_days'          => 1,
                'views_days'          => 1,
                'sessions_minutes'    => 480,
                'temp_days'           => 1,
                'dumps_days'          => 7,
                'dumps_keep_newest'   => 2,
            ],
        ],
    ],
];
