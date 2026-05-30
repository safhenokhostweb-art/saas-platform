# OTA Runtime Governance — Phase M1.0

## What OTA Governs

Over-The-Air governance means the mobile app has **no hardcoded UI logic, navigation, or feature flags**. On every launch, the app downloads its runtime configuration from the platform:

- Feature flags (which modules are enabled)
- Navigation schema (tabs, routes, roles)
- UI schema (component layout per role)
- Theme tokens (colors, typography, spacing)
- Security policies (min OS version, jailbreak block)
- OTA update policies (mandatory/soft/blocked versions)

## Manifest Endpoint

```
GET /api/mobile/runtime/manifest
Headers: X-App-Version: 1.2.3, Authorization: Bearer {token}
```

Response is cached 300s per `(tenantId, role, appVersion)` combination.

## Services

| Service | Responsibility |
|---|---|
| `MobileRuntimeManifestBuilder` | Assembles full OTA manifest from all governance services |
| `MobileRuntimeManifest` | Value object with checksum (SHA-256 of manifest content) |
| `MobileUpdateManifestBuilder` | Builds update policy manifest, signs with HMAC-SHA256 |
| `MobileFeatureBundleRegistry` | Manages installable feature bundles per tenant |
| `MobileUiSchemaRegistry` | Returns role-appropriate bottom nav and screen layout |
| `MobileThemeRuntime` | Manages theme tokens with 25+ design tokens |
| `MobileVersionEnforcementService` | Enforces min/blocked version policies |
| `MobileMandatoryUpdatePolicy` | Schedules mandatory updates with notice periods |
| `MobileSecurityPatchRuntime` | Tracks OTA security patch delivery per tenant |

## Update Actions

| Action | Trigger | App Behavior |
|---|---|---|
| `none` | Version OK | Normal launch |
| `soft_update` | Version < recommended | Show dismissible update prompt |
| `hard_update` | Version < minimum | Block launch, force update (HTTP 426) |
| `blocked` | Version in blocked list | Block launch with message |

## Signing

OTA manifests are signed with `HMAC-SHA256` using `config('app.key')`. The mobile app verifies the signature before applying any manifest. Set `MOBILE_OTA_SIGNING=false` to disable for local dev.

## Rollout Channels

| Channel | Delay | Rollout % |
|---|---|---|
| `canary` | 0 days | 5% of tenants |
| `beta` | 1 day | 20% of tenants |
| `stable` | 7 days | 100% of tenants |

## Artisan Commands

```bash
php artisan mobile:validate-ota            # Validate OTA runtime configuration
php artisan mobile:validate-ota --strict   # Treat warnings as failures
php artisan mobile:validate-ota --json     # JSON output for CI
```
