# Governance Guide — Phase M2.1

## Production Client Validator

Run the M2.1 governance validator at any time:

```bash
php artisan mobile:validate-production-client
php artisan mobile:validate-production-client --json   # Machine-readable output
php artisan mobile:validate-production-client --fail-fast  # Stop on first failing dimension
```

## Scoring Dimensions

| # | Dimension | Points | What is Checked |
|---|-----------|--------|-----------------|
| 1 | Transport | 10 | Reverb config, broadcast driver, Redis cache, realtime auth route |
| 2 | Offline | 10 | OfflineSyncService files, attachment queuing, conflict resolution, retry counter |
| 3 | Updates | 10 | OTA manifest route, stable/beta/canary channels, Windows+Android runtimes, checksum |
| 4 | Notifications | 10 | ClientNotificationRuntime, VoiceNotificationRuntime, transport priority chain |
| 5 | Security | 10 | SecureTokenService, DeviceTrustManager, token refresh, Sanctum auth |
| 6 | Attachments | 10 | AttachmentUploadService, multipart, MIME whitelist, size limit, validation exception |
| 7 | Deep Links | 10 | ConversationDeepLinkService, support:// schemes, handleUri, cold-start |
| 8 | Dashboard | 10 | SupportDashboardScreen, all 6 stat widgets, dashboard API endpoint |
| 9 | Assignment | 10 | ConversationAssignmentService, all 5 methods, audit event broadcast |
| 10 | Presence | 10 | ConversationPresenceService, all 5 methods, PresenceRuntimeManager delegation |

**Total: 100 points. Build fails if score < 95.**

## CI/CD Integration

The governance check is **Job 2** in `mobile-build.yml`. All platform builds (Android, Windows, Linux, macOS) **depend on** the governance job. A score < 95 fails the entire build pipeline.

```yaml
build-android:
  needs: [analyze, governance]   # Build blocked until governance passes
```

To bypass in an emergency (not recommended):
```yaml
workflow_dispatch:
  inputs:
    skip_governance:
      default: false
```

## Fixing Common Failures

| Dimension | Common Cause | Fix |
|-----------|-------------|-----|
| Transport | `BROADCAST_DRIVER` not set to `reverb` | Set in `.env`: `BROADCAST_DRIVER=reverb` |
| Offline | `OfflineSyncService` missing `enqueueAttachment` | Ensure updated file is deployed |
| Updates | OTA route not registered | Check `mobile-api.php` routes are loaded |
| Notifications | `ClientNotificationRuntime` file missing | Verify `mobile/lib/core/notifications/` directory |
| Security | `DeviceTrustManager` class not found | Verify package autoload is regenerated: `composer dump-autoload` |
| Attachments | MIME types missing from whitelist | Check `attachment_upload_service.dart` |
| Dashboard | Dashboard route not registered | Add `GET /api/mobile/dashboard` to routes |
| Assignment | `ConversationAssignmentService` methods missing | Run `composer dump-autoload` |

## M1.0 Baseline Commands (still required)

```bash
php artisan mobile:validate-client-runtime --json
php artisan mobile:validate-client-security --json
php artisan mobile:validate-client-governance --json
php artisan mobile:validate-client-updates --json
```

All M1.0 validators remain in CI and must continue to pass.
