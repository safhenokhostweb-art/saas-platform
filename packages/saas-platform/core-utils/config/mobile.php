<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile Runtime — Phase M1.0
    | Self-Hosted Mobile Communication Runtime Foundation
    |--------------------------------------------------------------------------
    */

    // ─── FCM Push Transport (carrier only — NOT a runtime authority) ──────────
    'fcm_server_key'  => env('MOBILE_FCM_SERVER_KEY', ''),
    'fcm_project_id'  => env('MOBILE_FCM_PROJECT_ID', ''),

    // ─── APNs (stub — future iOS push transport) ──────────────────────────────
    'apns_key_id'     => env('MOBILE_APNS_KEY_ID', ''),
    'apns_team_id'    => env('MOBILE_APNS_TEAM_ID', ''),
    'apns_bundle_id'  => env('MOBILE_APNS_BUNDLE_ID', 'com.example.app'),

    // ─── Version Policy ───────────────────────────────────────────────────────
    'min_app_version' => env('MOBILE_MIN_APP_VERSION', '1.0.0'),

    // ─── OTA Governance ───────────────────────────────────────────────────────
    'ota' => [
        'manifest_ttl_seconds'   => (int) env('MOBILE_OTA_MANIFEST_TTL', 300),
        'signing_enabled'        => (bool) env('MOBILE_OTA_SIGNING', true),
        'canary_rollout_pct'     => 5,
        'beta_rollout_pct'       => 20,
        'stable_rollout_pct'     => 100,
    ],

    // ─── Presence TTL ─────────────────────────────────────────────────────────
    'presence' => [
        'device_ttl_seconds'     => (int) env('MOBILE_PRESENCE_DEVICE_TTL', 120),
        'tenant_index_ttl'       => (int) env('MOBILE_PRESENCE_INDEX_TTL', 7200),
    ],

    // ─── Session Governance ───────────────────────────────────────────────────
    'session' => [
        'ttl_seconds'            => (int) env('MOBILE_SESSION_TTL', 86400),
        'max_per_user'           => (int) env('MOBILE_SESSION_MAX_PER_USER', 10),
    ],

    // ─── Notification Quotas ──────────────────────────────────────────────────
    'notifications' => [
        'daily_limit'            => (int) env('MOBILE_NOTIF_DAILY_LIMIT', 10000),
        'hourly_limit'           => (int) env('MOBILE_NOTIF_HOURLY_LIMIT', 500),
        'quiet_hours_start'      => env('MOBILE_NOTIF_QUIET_START', '22:00'),
        'quiet_hours_end'        => env('MOBILE_NOTIF_QUIET_END', '08:00'),
    ],

    // ─── Security Governance ──────────────────────────────────────────────────
    'security' => [
        'min_ios_version'        => env('MOBILE_MIN_IOS_VERSION', '15.0'),
        'min_android_version'    => env('MOBILE_MIN_ANDROID_VERSION', '10'),
        'device_trust_window'    => (int) env('MOBILE_DEVICE_TRUST_WINDOW', 600),
        'max_failed_attempts'    => (int) env('MOBILE_MAX_FAIL_ATTEMPTS', 5),
        'jailbreak_block'        => (bool) env('MOBILE_JAILBREAK_BLOCK', true),
    ],

    // ─── Reverb / WebSocket ───────────────────────────────────────────────────
    'reverb' => [
        'socket_auth_ttl'        => (int) env('MOBILE_SOCKET_AUTH_TTL', 300),
    ],

    // ─── Telemetry ────────────────────────────────────────────────────────────
    'telemetry' => [
        'max_events_per_tenant'  => (int) env('MOBILE_TELEMETRY_MAX_EVENTS', 1000),
        'max_crashes_per_tenant' => (int) env('MOBILE_TELEMETRY_MAX_CRASHES', 500),
        'max_latency_samples'    => (int) env('MOBILE_TELEMETRY_MAX_LATENCY', 500),
    ],

    // ─── Unified Client Runtime — Phase M1.1 ─────────────────────────────────
    // Supported client platforms (no platform-specific assumptions beyond this list)
    'client' => [
        'supported_platforms' => ['android', 'ios', 'windows', 'linux', 'macos', 'web'],
        'desktop_platforms'   => ['windows', 'linux', 'macos'],
        'mobile_platforms'    => ['android', 'ios'],
    ],

    // ─── Notification Transport Priority ─────────────────────────────────────
    // Priority 1: Reverb Realtime (WebSocket) — all platforms
    // Priority 2: Firebase Push (optional, mobile only)
    // Priority 3: Local Notification Recovery (desktop)
    // Priority 4: Offline Sync Recovery (all platforms, last resort)
    'notification_transport' => [
        'firebase_optional'          => true,   // system operates without Firebase
        'local_notification_desktop' => true,   // desktop OS notifications enabled
        'sync_recovery_ttl_hours'    => 24,     // offline notification TTL
        'local_pending_limit'        => 100,    // max pending per device
        'sync_missed_limit'          => 200,    // max missed per device
    ],

    // ─── Feature Flags (platform-level defaults, overridable per tenant) ──────
    'feature_flags' => [
        'chat'            => (bool) env('MOBILE_FEATURE_CHAT', true),
        'tickets'         => (bool) env('MOBILE_FEATURE_TICKETS', true),
        'notifications'   => (bool) env('MOBILE_FEATURE_NOTIFICATIONS', true),
        'presence'        => (bool) env('MOBILE_FEATURE_PRESENCE', true),
        'realtime'        => (bool) env('MOBILE_FEATURE_REALTIME', true),
        'ota'             => (bool) env('MOBILE_FEATURE_OTA', true),
        'voice'           => (bool) env('MOBILE_FEATURE_VOICE', false),
        'file_upload'     => (bool) env('MOBILE_FEATURE_FILE_UPLOAD', true),
        'ai_assistant'    => (bool) env('MOBILE_FEATURE_AI', false),
    ],

];
