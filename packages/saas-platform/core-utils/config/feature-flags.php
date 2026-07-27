<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags Configuration
    |--------------------------------------------------------------------------
    |
    | این فایل برای مدیریت feature flags سراسری استفاده می‌شود.
    | هر tenant (Reseller/Site) می‌تواند این flags را بازنویسی کند.
    |
    | ساختار: 'feature_name' => true|false
    |
    | اولویت بررسی:
    | 1. config('feature-flags.{feature}') — مقدار سراسری
    | 2. $tenant->features[{feature}] — مقدار مخصوص tenant (JSON field)
    | 3. $tenant->{feature} — فیلد مستقیم روی مدل tenant
    */

    // Tickets Module
    'tickets.attachments' => env('FEATURE_TICKETS_ATTACHMENTS', true),
    'tickets.sla' => env('FEATURE_TICKETS_SLA', true),
    'tickets.auto_assign' => env('FEATURE_TICKETS_AUTO_ASSIGN', false),
    'tickets.canned_replies' => env('FEATURE_TICKETS_CANNED_REPLIES', false),

    // SLA Module
    'sla.enabled' => env('FEATURE_SLA_ENABLED', true),
    'sla.business_hours_custom' => env('FEATURE_SLA_CUSTOM_HOURS', false),

    // General
    'debug_mode' => env('FEATURE_DEBUG_MODE', false),

    // Motion Graphics SaaS Integration (Phase 1 — Adapter Foundation)
    // Disabled: existing standalone-module behavior is unchanged.
    // Enabled: the Laravel adapter layer (app/Modules/MotionGraphics) becomes reachable.
    // See docs/motion-saas-integration/FEATURE_FLAG_STRATEGY.md.
    'motion_graphics_integration' => env('FEATURE_MOTION_GRAPHICS_INTEGRATION', false),
];
