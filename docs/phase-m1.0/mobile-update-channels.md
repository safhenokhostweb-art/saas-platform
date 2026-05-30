# Mobile Update Channels — Phase M1.0

## Channels

The platform governs three update channels for staged rollouts:

| Channel | Delay After Release | Max Rollout |
|---|---|---|
| `canary` | 0 days | 5% of tenant population |
| `beta` | 1 day | 20% of tenant population |
| `stable` | 7 days | 100% (all tenants) |

## Channel Assignment

`MobileUpdateChannelManager::assignChannel($tenantId, $channel)`:
- Stores channel in Cache: `mobile:update_channels:tenant:{tenantId}`
- Maintains per-channel index: `mobile:update_channels:idx:{channel}`

## Channel Migration

`migrateChannel($tenantId, $fromChannel, $toChannel)`:
- Removes tenant from old channel index
- Assigns to new channel
- Returns false if tenant was not in the expected channel (safety check)

## Version Policies

`MobileVersionPolicyManager` stores policies per tenant and channel:

```php
[
    'min_version'    => '1.0.0',   // hard_update if below
    'recommended'    => '1.2.0',   // soft_update if below
    'blocked'        => [],         // blocked version strings
    'effective_from' => '2026-01-01T00:00:00Z',
]
```

Default platform policy is returned if no tenant-specific policy is set.

## Mandatory Updates

`MobileMandatoryUpdatePolicy::scheduleUpdate()`:
- Generates UUID update ID
- Stores in Cache with scheduled datetime and target version
- `generateNotice()` returns:
  - `none` — update in future, no urgency
  - `warning` — within 3 days
  - `critical` — within 1 day

## Enforcement

`MobileVersionEnforcementService::enforce($tenantId, $currentVersion)`:
1. Check blocked versions list → return `blocked` action
2. Check if version < min → return `hard_update`
3. Check if version < recommended → return `soft_update`
4. Return `none` (version is acceptable)

When `hard_update` is returned by the manifest endpoint, HTTP 426 Upgrade Required is sent.
