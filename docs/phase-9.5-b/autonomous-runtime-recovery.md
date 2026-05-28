# Autonomous Runtime Recovery — Phase 9.5-B

## Overview

The runtime recovery system provides self-healing capabilities for frontend failures, realtime disconnects, AI provider outages, and asset mismatches — without requiring operator intervention.

## Recovery Orchestrator

`RuntimeRecoveryOrchestrator` coordinates all recovery subsystems:

```php
$recovery->runFullRecovery('scheduled');
// Returns:
// {
//   "frontend": { "dimensions": [...], "healthy": true },
//   "realtime": { "actions": [...], "healthy": true },
//   "ai":       { "actions": [...], "healthy": true },
//   "assets":   { "actions": [...], "healthy": true },
//   "routes":   { "actions": [...], "healthy": true },
//   "healthy":  true
// }
```

## Frontend Self-Healing

`FrontendSelfHealingEngine` checks and auto-repairs:

| Dimension              | Check                              | Repair Action             |
|------------------------|------------------------------------|---------------------------|
| blade_namespaces       | namespace hints registered         | Diagnostic only           |
| view_cache             | `storage/framework/views` exists   | Creates directory          |
| core_ui_components     | critical components present        | Diagnostic only           |
| asset_manifest         | mix-manifest.json exists           | Reports missing modules   |
| alpine_store_integrity | Alpine stores loaded               | Client-side recovery      |
| modal_registry         | No deadlocked modals               | Client-side ESC injection |
| builder_sync           | SortableJS state consistent        | Client-side resync        |

## Realtime Recovery

`RealtimeRecoveryCoordinator` handles:
- Transport detection → automatic polling fallback
- Stale reconnection counter reset
- Disconnection telemetry recording

## AI Runtime Recovery

Auto-recovery actions:
- Stale circuit breaker reset (after 10 min)
- Quota lock flush
- Kill switch state check (manual intervention required if active)

## Asset Recovery

Detects missing `public/modules/{forms,tickets}` symlinks and reports `needs_repair` status.

## Recovery Schedule

Recommended `app/Console/Kernel.php` schedule:

```php
$schedule->call(function () {
    app(RuntimeRecoveryOrchestrator::class)->runFullRecovery('scheduled');
})->everyFiveMinutes();
```

## Recovery Log

All recovery runs logged to cache: `runtime:recovery:log` (last 100 runs, 7-day TTL)

Also emitted via `Log::info('[RuntimeRecovery] ...')` for log aggregation.

## Commands

```bash
php artisan enterprise:validate-runtime-health
php artisan enterprise:validate-runtime-health --json --strict
```
