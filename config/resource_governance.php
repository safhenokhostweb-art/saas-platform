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
    'alert_critical_percent' => (int) env('RESOURCE_ALERT_CRITICAL_PCT', 95),

    // Snapshot retention (days) — old daily snapshots purged after this
    'snapshot_retention_days' => (int) env('RESOURCE_SNAPSHOT_RETENTION_DAYS', 90),

    // Archive growth anomaly threshold (bytes/day to trigger alert)
    'archive_growth_anomaly_bytes' => (int) env('RESOURCE_ARCHIVE_GROWTH_ANOMALY', 104857600), // 100 MB/day
];
